<?php

namespace Tests\Feature\Blog;

use App\Enums\BlogPostStatus;
use App\Models\ApiAgent;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->token = ApiAgent::create(['name' => 'blog-editor'])->createToken('triage', [ApiAgent::BLOG_ABILITY])->plainTextToken;
    }

    private function api(): static
    {
        return $this->withToken($this->token)->withHeader('Accept', 'application/json');
    }

    public function test_the_api_needs_an_active_agent_token_with_the_blog_ability()
    {
        $this->getJson('/api/blog/v1/posts')->assertUnauthorized();
        $this->withToken('nonsense')->getJson('/api/blog/v1/posts')->assertUnauthorized();

        $readOnly = ApiAgent::create(['name' => 'reader'])->createToken('x', ['other'])->plainTextToken;
        $this->withToken($readOnly)->getJson('/api/blog/v1/posts')->assertForbidden();

        ApiAgent::where('name', 'blog-editor')->update(['is_active' => false]);
        $this->api()->getJson('/api/blog/v1/posts')->assertForbidden();
    }

    public function test_a_signed_in_admin_session_is_not_an_api_token()
    {
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/blog/v1/posts')->assertUnauthorized();
    }

    public function test_posts_can_be_listed_and_filtered()
    {
        $category = BlogCategory::factory()->create(['slug' => 'o-kofe']);
        BlogPost::factory()->for($category, 'category')->draft()->create(['slug' => 'draft-one', 'source' => 'archive']);
        BlogPost::factory()->create(['slug' => 'live-one', 'triage_decision' => 'publish', 'triaged_at' => now()]);

        $this->api()->getJson('/api/blog/v1/posts')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->api()->getJson('/api/blog/v1/posts?status=draft&category=o-kofe&source=archive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'draft-one');
        $this->api()->getJson('/api/blog/v1/posts?triage=none')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'draft-one');
        $this->api()->getJson('/api/blog/v1/posts?status=nonsense')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_one_post_comes_with_its_markdown_html_and_facts()
    {
        BlogPost::factory()->create(['slug' => 'turka', 'body' => "## Шаг\n\nТекст.", 'import_meta' => ['images_missing' => 2]]);

        $this->api()->getJson('/api/blog/v1/posts/turka')
            ->assertOk()
            ->assertJsonPath('data.body', "## Шаг\n\nТекст.")
            ->assertJsonPath('data.html', fn (string $html) => str_contains($html, '<h2 id="shag">Шаг</h2>'))
            ->assertJsonPath('data.revisions_count', 0)
            ->assertJsonPath('data.images_missing', 2);

        $id = BlogPost::where('slug', 'turka')->value('id');
        $this->api()->getJson("/api/blog/v1/posts/{$id}")->assertOk()->assertJsonPath('data.slug', 'turka');
    }

    public function test_an_agent_creates_a_draft_with_a_revision_in_its_name()
    {
        BlogCategory::factory()->create(['slug' => 'recepty-kofe']);

        $this->api()->postJson('/api/blog/v1/posts', [
            'title' => 'Раф с лавандой',
            'body' => 'Рецепт.',
            'category' => 'recepty-kofe',
            'tags' => ['раф', 'рецепт'],
            'note' => 'Новый рецепт',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'raf-s-lavandoy')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source', 'agent')
            ->assertJsonPath('data.category', 'recepty-kofe')
            ->assertJsonPath('data.tags', ['раф', 'рецепт']);

        $revision = BlogPost::where('slug', 'raf-s-lavandoy')->first()->revisions()->first();
        $this->assertSame('agent: blog-editor (triage)', $revision->author);
        $this->assertSame('Новый рецепт', $revision->note);

        $this->api()->postJson('/api/blog/v1/posts', ['body' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->api()->postJson('/api/blog/v1/posts', ['title' => 'x', 'body' => 'x', 'category' => 'nope'])->assertJsonValidationErrors('category');
    }

    public function test_editing_records_a_revision_and_restoring_brings_text_back()
    {
        $post = BlogPost::factory()->create(['slug' => 'turka', 'body' => 'Старый текст.']);

        $this->api()->patchJson('/api/blog/v1/posts/turka', ['body' => 'Новый текст.', 'note' => 'Обновил'])->assertOk()->assertJsonPath('data.body', 'Новый текст.');
        $this->assertNotNull($post->fresh()->edited_at);

        // Triage fields alone do not create a revision.
        $this->api()->patchJson('/api/blog/v1/posts/turka', ['triage_decision' => 'publish', 'triage_confidence' => 90, 'triage_reason' => 'Вечнозелёная статья'])
            ->assertOk()->assertJsonPath('data.triage.decision', 'publish');
        $this->assertNotNull($post->fresh()->triaged_at);

        $revisions = $this->api()->getJson('/api/blog/v1/posts/turka/revisions')->assertOk()->json('data');
        $this->assertCount(1, $revisions);

        $this->api()->patchJson('/api/blog/v1/posts/turka', ['body' => 'Третий текст.'])->assertOk();
        $first = $revisions[0]['id'];

        $this->api()->getJson("/api/blog/v1/posts/turka/revisions/{$first}")->assertJsonPath('data.body', 'Новый текст.');
        $this->api()->postJson("/api/blog/v1/posts/turka/revisions/{$first}/restore")->assertOk()->assertJsonPath('data.body', 'Новый текст.');
        $this->assertSame("Restored revision #{$first}", $post->revisions()->first()->note);
    }

    public function test_publishing_keeps_the_original_date_and_points_old_addresses_here()
    {
        $post = BlogPost::factory()->draft()->create(['slug' => 'kofe-v-dzhezve', 'published_at' => '2017-09-18', 'legacy_url' => '/brew/cezve']);
        Redirect::updateOrCreate(['from_path' => '/brew/cezve'], ['to_path' => '/coffee/cezve', 'status_code' => 301]);

        $this->api()->postJson('/api/blog/v1/posts/kofe-v-dzhezve/publish')->assertOk()->assertJsonPath('data.status', 'published');

        $this->assertSame('2017-09-18', $post->fresh()->published_at->toDateString());
        $this->assertSame('/blog/kofe-v-dzhezve', Redirect::where('from_path', '/brew/cezve')->value('to_path'));
        $this->get('/blog/kofe-v-dzhezve')->assertOk();
        $this->get('/brew/cezve')->assertRedirect('/blog/kofe-v-dzhezve');

        $this->api()->postJson('/api/blog/v1/posts/kofe-v-dzhezve/unpublish')->assertJsonPath('data.status', 'draft');
        $this->get('/blog/kofe-v-dzhezve')->assertNotFound();
    }

    public function test_a_future_date_schedules_the_post()
    {
        BlogPost::factory()->draft()->create(['slug' => 'soon', 'published_at' => null]);

        $this->api()->postJson('/api/blog/v1/posts/soon/publish', ['published_at' => now()->addDays(2)->toAtomString()])
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.is_visible', false);
    }

    public function test_archiving_retires_the_post_with_a_redirect()
    {
        BlogPost::factory()->create(['slug' => 'birthday_lottery_2018']);

        $this->api()->postJson('/api/blog/v1/posts/birthday_lottery_2018/archive', ['redirect_to' => 'https://evil.example/x'])
            ->assertUnprocessable()->assertJsonValidationErrors('redirect_to');
        $this->api()->postJson('/api/blog/v1/posts/birthday_lottery_2018/archive', ['redirect_to' => '/blog/birthday_lottery_2018'])
            ->assertUnprocessable()->assertJsonValidationErrors('redirect_to');

        $this->api()->postJson('/api/blog/v1/posts/birthday_lottery_2018/archive', ['redirect_to' => '/blog/c/kofeinye-novosti', 'note' => 'Устаревший розыгрыш'])
            ->assertOk()->assertJsonPath('data.status', 'archived');

        $this->get('/blog/birthday_lottery_2018')->assertRedirect('/blog/c/kofeinye-novosti')->assertStatus(301);

        // Bringing it back removes the redirect.
        $this->api()->postJson('/api/blog/v1/posts/birthday_lottery_2018/publish')->assertOk();
        $this->get('/blog/birthday_lottery_2018')->assertOk();
    }

    public function test_renaming_a_live_post_redirects_the_old_address()
    {
        BlogPost::factory()->create(['slug' => 'old-name']);

        $this->api()->patchJson('/api/blog/v1/posts/old-name', ['slug' => 'new-name'])->assertOk();

        $this->get('/blog/old-name')->assertRedirect('/blog/new-name');
        $this->api()->patchJson('/api/blog/v1/posts/new-name', ['slug' => 'bad slug!'])->assertJsonValidationErrors('slug');
    }

    public function test_deleting_moves_a_post_to_the_bin()
    {
        BlogPost::factory()->create(['slug' => 'gone']);

        $this->api()->deleteJson('/api/blog/v1/posts/gone', ['redirect_to' => '/blog'])->assertOk();

        $this->assertSoftDeleted('blog_posts', ['slug' => 'gone']);
        $this->api()->getJson('/api/blog/v1/posts?trashed=1')->assertJsonPath('data.0.slug', 'gone');
        $this->get('/blog/gone')->assertRedirect('/blog');
    }

    public function test_images_upload_as_webp_and_cannot_be_deleted_while_used()
    {
        $response = $this->api()->post('/api/blog/v1/images', [
            'file' => UploadedFile::fake()->image('turka.jpg', 2400, 1600),
            'alt' => 'Турка на песке',
        ])->assertCreated()->assertJsonPath('data.width', BlogImage::LARGE_WIDTH)->assertJsonPath('data.alt', 'Турка на песке');

        $image = BlogImage::findOrFail($response->json('data.id'));
        Storage::disk('public')->assertExists([$image->path, $image->thumb_path]);
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertSame("![Турка на песке](/storage/{$image->path})", $response->json('data.markdown'));
        $this->assertSame('agent: blog-editor (triage)', $image->uploaded_by);

        $this->api()->postJson('/api/blog/v1/posts', ['title' => 'С картинкой', 'body' => $response->json('data.markdown')])->assertCreated();
        $this->api()->deleteJson("/api/blog/v1/images/{$image->id}")->assertStatus(409);

        $this->api()->post('/api/blog/v1/images', ['file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_image_addresses_on_private_networks_are_refused()
    {
        $this->api()->postJson('/api/blog/v1/images', ['source_url' => 'http://127.0.0.1/secret.png'])
            ->assertUnprocessable()->assertJsonValidationErrors('source_url');
    }

    public function test_sections_tags_products_and_redirects()
    {
        $this->api()->postJson('/api/blog/v1/categories', ['slug' => 'oborudovanie', 'name' => 'Оборудование'])->assertCreated();
        $id = BlogCategory::where('slug', 'oborudovanie')->value('id');
        $this->api()->getJson('/api/blog/v1/categories')->assertJsonPath('data.0.slug', 'oborudovanie');

        BlogPost::factory()->create(['blog_category_id' => $id]);
        $this->api()->deleteJson("/api/blog/v1/categories/{$id}")->assertStatus(409);

        $this->api()->getJson('/api/blog/v1/tags')->assertOk();
        $this->api()->getJson('/api/blog/v1/products?search=кения')->assertOk()->assertJsonStructure(['data']);

        $this->api()->postJson('/api/blog/v1/redirects', ['from' => '/blog/old-a', 'to' => '/blog/new-b'])->assertCreated();
        $this->get('/blog/old-a')->assertRedirect('/blog/new-b');
        $this->api()->postJson('/api/blog/v1/redirects', ['from' => 'https://x.ru/a', 'to' => '/b'])->assertJsonValidationErrors('from');
    }

    public function test_the_schema_describes_the_api()
    {
        $this->api()->getJson('/api/blog/v1/schema')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonStructure(['paths' => ['/posts', '/posts/{post}/archive', '/images', '/products', '/redirects']]);
    }

    public function test_the_archive_status_can_also_be_set_through_patch()
    {
        BlogPost::factory()->create(['slug' => 'stub']);

        $this->api()->patchJson('/api/blog/v1/posts/stub', ['status' => 'archived', 'redirect_to' => '/blog'])->assertOk();

        $this->assertSame(BlogPostStatus::Archived, BlogPost::where('slug', 'stub')->first()->status);
        $this->get('/blog/stub')->assertRedirect('/blog');
    }
}
