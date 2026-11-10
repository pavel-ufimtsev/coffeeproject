<?php

namespace Tests\Feature\Admin;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    public function test_customers_cannot_open_the_blog_admin()
    {
        $post = BlogPost::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('admin.blog.posts.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('admin.blog.posts.update', $post), ['title' => 'x', 'body' => 'y'])->assertForbidden();
    }

    public function test_every_blog_admin_page_renders()
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->for($category, 'category')->create();

        $pages = [
            route('admin.blog.posts.index') => 'admin/blog/posts/Index',
            route('admin.blog.posts.create') => 'admin/blog/posts/Edit',
            route('admin.blog.posts.edit', $post) => 'admin/blog/posts/Edit',
            route('admin.blog.images.index') => 'admin/blog/images/Index',
            route('admin.blog.categories.index') => 'admin/blog/categories/Index',
            route('admin.blog.categories.create') => 'admin/blog/categories/Form',
            route('admin.blog.categories.edit', $category) => 'admin/blog/categories/Form',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_the_needs_a_human_filter_lists_drafts_the_agent_left()
    {
        BlogPost::factory()->draft()->create(['slug' => 'left-for-a-human', 'triage_decision' => 'refresh', 'triage_confidence' => 40, 'triaged_at' => now()]);
        BlogPost::factory()->draft()->create(['slug' => 'not-reviewed']);
        BlogPost::factory()->create(['slug' => 'published', 'triage_decision' => 'publish', 'triaged_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.blog.posts.index', ['attention' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.attention', 1)
                ->has('posts.data', 1)
                ->where('posts.data.0.slug', 'left-for-a-human')
                ->where('posts.data.0.triage.confidence', 40));

        $this->actingAs($this->admin)->get(route('admin.blog.posts.index', ['triage' => 'none']))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.slug', 'not-reviewed'));
    }

    public function test_an_admin_writes_a_draft_and_edits_it_with_revisions_in_their_name()
    {
        $category = BlogCategory::factory()->create(['slug' => 'recepty-kofe']);

        $this->actingAs($this->admin)->post(route('admin.blog.posts.store'), [
            'title' => 'Раф с лавандой',
            'body' => 'Нежный раф.',
            'category' => 'recepty-kofe',
            'tags' => ['раф'],
            'noindex' => false,
        ])->assertRedirect();

        $post = BlogPost::where('slug', 'raf-s-lavandoy')->firstOrFail();
        $this->assertSame(BlogPostStatus::Draft, $post->status);
        $this->assertSame('admin', $post->source);
        $this->assertSame($category->id, $post->blog_category_id);

        $this->actingAs($this->admin)->put(route('admin.blog.posts.update', $post), [
            'title' => 'Раф с лавандой',
            'slug' => 'raf-s-lavandoy',
            'body' => 'Нежный раф с лавандовым сиропом.',
            'noindex' => false,
            'note' => 'Уточнил рецепт',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $revision = $post->revisions()->first();
        $this->assertSame($this->admin->name, $revision->author);
        $this->assertSame($this->admin->id, $revision->user_id);
        $this->assertSame('Уточнил рецепт', $revision->note);
        $this->assertSame(2, $post->revisions()->count());
    }

    public function test_publishing_and_retiring_from_the_admin()
    {
        $target = BlogPost::factory()->create(['slug' => 'turka-guide']);
        $post = BlogPost::factory()->draft()->create(['slug' => 'old-turka', 'published_at' => '2018-03-01 10:00:00']);

        $this->actingAs($this->admin)->post(route('admin.blog.posts.status', $post), ['action' => 'publish'])->assertRedirect();
        $post->refresh();
        $this->assertSame(BlogPostStatus::Published, $post->status);
        $this->assertSame('2018-03-01', $post->published_at->toDateString());

        $this->actingAs($this->admin)->post(route('admin.blog.posts.status', $post), ['action' => 'archive', 'redirect_to' => '/blog/turka-guide'])->assertRedirect();
        $this->assertSame(BlogPostStatus::Archived, $post->refresh()->status);
        $this->assertSame($target->path(), Redirect::where('from_path', '/blog/old-turka')->value('to_path'));

        $this->actingAs($this->admin)->post(route('admin.blog.posts.status', $post), ['action' => 'archive', 'redirect_to' => 'https://evil.example'])
            ->assertSessionHasErrors('redirect_to');
    }

    public function test_bulk_actions_change_many_posts()
    {
        $posts = BlogPost::factory()->draft()->count(3)->create();

        $this->actingAs($this->admin)->post(route('admin.blog.posts.bulk'), ['action' => 'publish', 'ids' => $posts->pluck('id')->all()])->assertRedirect();
        $this->assertSame(3, BlogPost::where('status', BlogPostStatus::Published)->count());

        $this->actingAs($this->admin)->post(route('admin.blog.posts.bulk'), ['action' => 'archive', 'redirect_to' => '/blog', 'ids' => $posts->pluck('id')->all()])->assertRedirect();
        $this->assertSame(3, BlogPost::where('status', BlogPostStatus::Archived)->count());
        $this->assertSame(3, Redirect::where('to_path', '/blog')->count());
    }

    public function test_revisions_can_be_compared_and_restored()
    {
        $post = BlogPost::factory()->create(['title' => 'Кофе', 'body' => 'Старый текст.']);
        $this->actingAs($this->admin)->put(route('admin.blog.posts.update', $post), ['title' => 'Кофе', 'slug' => $post->slug, 'body' => 'Новый текст.', 'noindex' => false]);
        $this->actingAs($this->admin)->put(route('admin.blog.posts.update', $post), ['title' => 'Кофе', 'slug' => $post->slug, 'body' => 'Новейший текст.', 'noindex' => false]);
        $first = $post->revisions()->get()->last();

        $this->actingAs($this->admin)->getJson(route('admin.blog.posts.revisions.show', [$post, $first]))
            ->assertOk()->assertJsonPath('body', 'Новый текст.');

        $this->actingAs($this->admin)->post(route('admin.blog.posts.revisions.restore', [$post, $first]))->assertRedirect();
        $this->assertSame('Новый текст.', $post->refresh()->body);

        $other = BlogPost::factory()->create();
        $this->actingAs($this->admin)->getJson(route('admin.blog.posts.revisions.show', [$other, $first]))->assertNotFound();
    }

    public function test_the_preview_renders_markdown_safely()
    {
        $this->actingAs($this->admin)->postJson(route('admin.blog.posts.preview'), [
            'body' => "## Шаг\n\n<script>alert(1)</script>\n\n::product[nope]",
        ])->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, '<h2 id="shag">Шаг</h2>')
                && ! str_contains($html, '<script>')
                && str_contains($html, 'blog-preview-product'));
    }

    public function test_images_upload_and_unused_ones_can_be_deleted()
    {
        $this->actingAs($this->admin)->postJson(route('admin.blog.images.store'), ['file' => UploadedFile::fake()->image('turka.jpg', 2000, 1200)])
            ->assertCreated()
            ->assertJsonPath('width', BlogImage::LARGE_WIDTH)
            ->assertJsonPath('markdown', fn (string $md) => str_starts_with($md, '![](/storage/blog/'));

        $image = BlogImage::firstOrFail();
        $this->assertSame($this->admin->name, $image->uploaded_by);

        $post = BlogPost::factory()->create(['body' => "![](/storage/{$image->path})"]);
        $this->actingAs($this->admin)->delete(route('admin.blog.images.destroy', $image))->assertSessionHasErrors('image');

        $post->update(['body' => 'Без картинки.']);
        $this->actingAs($this->admin)->delete(route('admin.blog.images.destroy', $image))->assertRedirect();
        $this->assertModelMissing($image);
    }

    public function test_renaming_a_section_redirects_its_old_address()
    {
        $category = BlogCategory::factory()->create(['slug' => 'old-name', 'name' => 'О кофе']);

        $this->actingAs($this->admin)->put(route('admin.blog.categories.update', $category), [
            'slug' => 'o-kofe', 'name' => 'О кофе', 'sort' => 1, 'is_active' => true,
        ])->assertRedirect(route('admin.blog.categories.index'));

        $this->assertSame('/blog/c/o-kofe', Redirect::where('from_path', '/blog/c/old-name')->value('to_path'));
    }

    public function test_deleted_posts_go_to_the_bin_and_come_back_as_drafts()
    {
        $post = BlogPost::factory()->draft()->create();

        $this->actingAs($this->admin)->delete(route('admin.blog.posts.destroy', $post))->assertRedirect(route('admin.blog.posts.index'));
        $this->assertSoftDeleted($post);

        $this->actingAs($this->admin)->post(route('admin.blog.posts.restore', $post->id))->assertRedirect(route('admin.blog.posts.edit', $post));
        $this->assertNotSoftDeleted($post);
    }
}
