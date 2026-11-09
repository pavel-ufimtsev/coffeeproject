<?php

namespace Tests\Feature\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ImportsCatalogFixture;
use Tests\TestCase;

class BlogPagesTest extends TestCase
{
    use ImportsCatalogFixture, RefreshDatabase;

    public function test_the_blog_lists_only_articles_readers_can_see()
    {
        BlogPost::factory()->create(['title' => 'Как варить в турке']);
        BlogPost::factory()->draft()->create(['title' => 'Черновик статьи']);
        BlogPost::factory()->archived()->create(['title' => 'Старая новость']);
        BlogPost::factory()->create(['title' => 'Будущая статья', 'status' => BlogPostStatus::Scheduled, 'published_at' => now()->addDay()]);
        BlogPost::factory()->create(['title' => 'Статья вышла по расписанию', 'status' => BlogPostStatus::Scheduled, 'published_at' => now()->subHour()]);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Как варить в турке')
            ->assertSee('Статья вышла по расписанию')
            ->assertDontSee('Черновик статьи')
            ->assertDontSee('Старая новость')
            ->assertDontSee('Будущая статья')
            ->assertSee('<link rel="canonical" href="'.url('/blog').'">', false);
    }

    public function test_the_blog_pages_twelve_articles_at_a_time()
    {
        BlogPost::factory()->count(13)->create();

        $this->get('/blog')->assertOk()->assertSee('href="'.url('/blog').'?page=2"', false);
        $this->get('/blog?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.url('/blog').'?page=2">', false);
        $this->get('/blog?page=3')->assertNotFound();
    }

    public function test_an_article_page_shows_the_text_and_structured_data()
    {
        $category = BlogCategory::factory()->create(['slug' => 'o-kofe', 'name' => 'О кофе']);
        $post = BlogPost::factory()->for($category, 'category')->create([
            'slug' => 'barista_declension',
            'title' => 'Как склонять слово бариста',
            'excerpt' => 'Короткий ответ филолога.',
            'body' => "## Мужской род\n\nТекст.\n\n## Женский род\n\nЕщё текст.\n\n## Итог\n\nВывод.",
            'published_at' => '2019-03-01 10:00:00',
        ]);

        $this->get('/blog/barista_declension')
            ->assertOk()
            ->assertSee('<h1>Как склонять слово бариста</h1>', false)
            ->assertSee('<h2 id="muzhskoy-rod">Мужской род</h2>', false)
            ->assertSee('href="#muzhskoy-rod"', false)
            ->assertSee('1 марта 2019')
            ->assertSee('href="'.url('/blog/c/o-kofe').'"', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"headline":"Как склонять слово бариста"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);

        $this->assertSame(1, $post->reading_minutes);
    }

    public function test_drafts_are_hidden_from_readers_and_previewed_by_admins()
    {
        BlogPost::factory()->draft()->create(['slug' => 'secret-draft', 'title' => 'Черновик']);

        $this->get('/blog/secret-draft')->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/blog/secret-draft')
            ->assertOk()
            ->assertSee('Предпросмотр')
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_the_slug_is_matched_exactly_and_other_spellings_redirect()
    {
        BlogPost::factory()->create(['slug' => 'announce-WCRC-2019']);

        $this->get('/blog/announce-WCRC-2019')->assertOk();
        $this->get('/blog/announce-wcrc-2019')->assertRedirect('/blog/announce-WCRC-2019')->assertStatus(301);
    }

    public function test_markdown_is_sanitised_and_embeds_are_rendered()
    {
        $this->importCatalogFixture();

        BlogPost::factory()->create([
            'slug' => 'embeds',
            'body' => "Текст <script>alert(1)</script> и [ссылка](javascript:alert(1)).\n\n::youtube[dQw4w9WgXcQ]\n\n::product[kenya-wolf]\n\n::product[no-such-coffee]\n\n| Метод | Помол |\n|---|---|\n| Турка | Пыль |",
        ]);

        $this->get('/blog/embeds')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Кения Волк')
            ->assertDontSee('no-such-coffee')
            ->assertSee('<div class="table-scroll"><table>', false);
    }

    public function test_category_pages_and_unknown_old_categories()
    {
        $category = BlogCategory::factory()->create(['slug' => 'recepty-kofe', 'name' => 'Рецепты']);
        BlogPost::factory()->for($category, 'category')->create(['title' => 'Раф с апельсином']);
        BlogPost::factory()->create(['title' => 'Другая статья']);

        $this->get('/blog/c/recepty-kofe')
            ->assertOk()
            ->assertSee('<h1>Рецепты</h1>', false)
            ->assertSee('Раф с апельсином')
            ->assertDontSee('Другая статья');

        $this->get('/blog/c/kfoe-profi')->assertRedirect('/blog')->assertStatus(301);
        $this->get('/blog')->assertSee('href="'.url('/blog/c/recepty-kofe').'"', false);
    }

    public function test_old_tag_pages_redirect_to_the_category_most_of_their_articles_are_in()
    {
        $recipes = BlogCategory::factory()->create(['slug' => 'recepty-kofe']);
        $news = BlogCategory::factory()->create(['slug' => 'novosti']);
        $tag = BlogTag::create(['slug' => 'raf', 'name' => 'раф']);

        foreach ([$recipes, $recipes, $news] as $category) {
            BlogPost::factory()->for($category, 'category')->create()->tags()->attach($tag);
        }

        $this->get('/blog/t/raf')->assertRedirect('/blog/c/recepty-kofe')->assertStatus(301);
        $this->get('/blog/t/1-aprelja')->assertRedirect('/blog')->assertStatus(301);
    }

    public function test_old_wordpress_addresses_redirect_or_are_gone()
    {
        BlogPost::factory()->create(['slug' => 'aromaster-2018-join']);
        BlogCategory::factory()->create(['slug' => 'coffee-at-home']);

        $this->get('/blog/aromaster-2018-join/amp')->assertRedirect('/blog/aromaster-2018-join')->assertStatus(301);
        $this->get('/blog/aromaster-2018-join/embed')->assertRedirect('/blog/aromaster-2018-join')->assertStatus(301);
        $this->get('/blog/amp')->assertRedirect('/blog')->assertStatus(301);
        $this->get('/blog/page/3')->assertRedirect('/blog?page=3')->assertStatus(301);
        $this->get('/blog/page/1')->assertRedirect('/blog')->assertStatus(301);
        $this->get('/blog/comments/feed')->assertRedirect('/blog/feed')->assertStatus(301);
        $this->get('/blog/topics/coffee-at-home')->assertRedirect('/blog/c/coffee-at-home')->assertStatus(301);
        $this->get('/blog/topics/cp-life')->assertRedirect('/blog')->assertStatus(301);
        $this->get('/blog/wp-json/wp/v2/categories/1')->assertStatus(410);
        $this->get('/blog/wp-admin/css/forms.min.css')->assertStatus(410);
        $this->get('/blog/xmlrpc.php')->assertStatus(410);
    }

    public function test_a_retired_article_redirects_through_the_redirects_table()
    {
        BlogPost::factory()->archived()->create(['slug' => 'birthday_lottery_2018']);
        Redirect::create(['from_path' => '/blog/birthday_lottery_2018', 'to_path' => '/blog', 'status_code' => 301]);

        $this->get('/blog/birthday_lottery_2018')->assertRedirect('/blog')->assertStatus(301);
    }

    public function test_the_feed_and_the_sitemap_list_visible_articles()
    {
        $category = BlogCategory::factory()->create(['slug' => 'o-kofe']);
        BlogPost::factory()->for($category, 'category')->create(['slug' => 'visible-post', 'title' => 'Видимая статья']);
        BlogPost::factory()->draft()->create(['slug' => 'hidden-post']);
        BlogPost::factory()->create(['slug' => 'noindex-post', 'noindex' => true]);

        $this->get('/blog/feed')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<title>Видимая статья</title>', false)
            ->assertDontSee('hidden-post');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/blog').'</loc>', false)
            ->assertSee('<loc>'.url('/blog/c/o-kofe').'</loc>', false)
            ->assertSee('<loc>'.url('/blog/visible-post').'</loc>', false)
            ->assertDontSee('hidden-post')
            ->assertDontSee('noindex-post');
    }

    public function test_every_page_links_the_blog_in_the_footer()
    {
        $this->get('/about')->assertSee('href="'.route('blog.index').'"', false);
    }
}
