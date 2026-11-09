<?php

namespace Tests\Feature\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogArchiveImportTest extends TestCase
{
    use RefreshDatabase;

    private string $archive;

    private string $bundle;

    private string $report;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $root = sys_get_temp_dir().'/blog-archive-test-'.uniqid();
        $this->archive = "{$root}/backups";
        $this->bundle = "{$root}/bundle";
        $this->report = "{$root}/report.csv";
        $this->makeArchive();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->archive));

        parent::tearDown();
    }

    public function test_it_extracts_and_imports_posts_as_drafts()
    {
        $this->artisan('blog:extract-archive', ['archive' => $this->archive, '--out' => $this->bundle])->assertSuccessful();
        $this->artisan('blog:import', ['--path' => $this->bundle, '--report' => $this->report])->assertSuccessful();

        // Two posts and the brewing guide; the folder without article markers is not a post.
        $this->assertSame(['how_make_coffee_in_cezve', 'kofe-v-dzhezve', 'zipped_post'], BlogPost::orderBy('slug')->pluck('slug')->all());
        $this->assertSame(0, BlogPost::where('status', '!=', BlogPostStatus::Draft)->count());

        $post = BlogPost::where('slug', 'how_make_coffee_in_cezve')->with('category', 'tags', 'cover', 'revisions')->first();
        $this->assertSame('Как заварить кофе в турке на плите', $post->title);
        $this->assertSame('Несколько советов о турке.', $post->excerpt);
        $this->assertSame('2018-05-21', $post->published_at->toDateString());
        $this->assertSame('o-kofe', $post->category->slug);
        $this->assertSame(['dzhezva', 'turka'], $post->tags->pluck('slug')->sort()->values()->all());
        $this->assertSame('archive', $post->source);
        $this->assertSame('Coffee Project', $post->author_name);
        $this->assertNotNull($post->cover);
        $this->assertCount(1, $post->revisions);

        // Markdown: clean headings, the video id kept, the table of contents and related posts dropped.
        $this->assertStringContainsString('## ШАГ ПЕРВЫЙ. Выбор кофе', $post->body);
        $this->assertStringContainsString('::youtube[qtXsqPOORSM]', $post->body);
        $this->assertStringContainsString('[кофе из Кении](/coffee/kenya)', $post->body);
        $this->assertStringNotContainsString('Содержание', $post->body);
        $this->assertStringNotContainsString('Читайте также', $post->body);
        $this->assertStringNotContainsString('alert', $post->body);

        // The largest archived size of the upload was used and stored as WebP; the missing one was dropped and recorded.
        $image = BlogImage::where('alt', 'Турка на плите')->firstOrFail();
        $this->assertSame(1200, $image->width);
        Storage::disk('public')->assertExists($image->path);
        $this->assertStringContainsString('](/storage/'.$image->path.')', $post->body);
        $this->assertStringNotContainsString('never-archived', $post->body);
        $this->assertSame(1, $post->import_meta['images_missing']);
        $this->assertTrue($post->images->contains($image));

        // The gzip-compressed page was read.
        $this->assertSame('Сжатая статья', BlogPost::where('slug', 'zipped_post')->value('title'));

        // The brewing guide keeps its old address for the redirect when it goes live.
        $guide = BlogPost::where('slug', 'kofe-v-dzhezve')->first();
        $this->assertSame('/brew/cezve', $guide->legacy_url);
        $this->assertSame('zavarivanie', $guide->category->slug);
        $this->assertStringContainsString('## Шаг первый', $guide->body);
        $this->assertStringNotContainsString('Где купить', $guide->body);

        // Sections and the old section addresses.
        $this->assertSame(6, BlogCategory::count());
        $this->assertSame('/blog/c/zavarivanie', Redirect::where('from_path', '/blog/c/kofevarki')->value('to_path'));

        $this->assertFileExists($this->report);
    }

    public function test_running_it_again_updates_untouched_posts_and_keeps_edited_ones()
    {
        $this->artisan('blog:extract-archive', ['archive' => $this->archive, '--out' => $this->bundle])->assertSuccessful();
        $this->artisan('blog:import', ['--path' => $this->bundle, '--report' => $this->report])->assertSuccessful();

        BlogPost::where('slug', 'zipped_post')->first()->update(['title' => 'Отредактировано', 'edited_at' => now()]);

        $this->artisan('blog:import', ['--path' => $this->bundle, '--report' => $this->report])->assertSuccessful();

        $this->assertSame('Отредактировано', BlogPost::where('slug', 'zipped_post')->value('title'));
        $this->assertSame(3, BlogPost::count());
        // Unchanged posts get no extra revision; images are not stored twice.
        $this->assertSame(1, BlogPost::where('slug', 'how_make_coffee_in_cezve')->first()->revisions()->count());
        $this->assertSame(BlogImage::count(), BlogImage::distinct()->count('original_name'));
    }

    public function test_a_dry_run_writes_nothing()
    {
        $this->artisan('blog:extract-archive', ['archive' => $this->archive, '--out' => $this->bundle])->assertSuccessful();
        $this->artisan('blog:import', ['--path' => $this->bundle, '--dry-run' => true, '--report' => $this->report])->assertSuccessful();

        $this->assertSame(0, BlogPost::count());
        $this->assertSame(0, BlogCategory::count());
    }

    private function makeArchive(): void
    {
        $site = "{$this->archive}/site";
        File::ensureDirectoryExists("{$this->archive}/_index");
        File::ensureDirectoryExists("{$site}/blog/wp-content/uploads/2018/05");

        // The upload exists only as two resized copies; the bigger one should win.
        $this->jpeg("{$site}/blog/wp-content/uploads/2018/05/turka-600x400.jpg", 600, 400);
        $this->jpeg("{$site}/blog/wp-content/uploads/2018/05/turka-1200x800.jpg", 1200, 800);
        $this->jpeg("{$site}/blog/wp-content/uploads/2018/05/cover.jpg", 800, 500);
        $this->jpeg("{$site}/img/brewguides/cezve/cezve_01.jpg", 640, 480);

        File::put("{$this->archive}/_index/manifest.csv", implode("\n", [
            'original_url,timestamp,mimetype,local_path,bytes,result,wayback_url,source,referenced_from,note',
            'https://www.coffeeproject.ru/brew/cezve,20170918000000,text/html,site/brew/cezve/index.html,1,saved,,page,,',
            'https://www.coffeeproject.ru/images/blog/82/never-archived.webp,20180601000000,image/webp,,0,missing,,asset,,',
        ])."\n");

        $page = fn (string $title, string $body) => <<<HTML
            <!doctype html><html><head><meta charset="utf-8">
            <meta property="og:title" content="{$title} - Coffee Project">
            <meta property="og:description" content="Несколько советов о турке.">
            <meta property="article:published_time" content="2018-05-21T06:32:54+00:00">
            <script>alert(1)</script></head><body>
            <div class="bem_imageHeader" style="background: #aaa url('https://www.coffeeproject.ru/blog/wp-content/uploads/2018/05/cover.jpg') no-repeat;">
              <p class="bem_imageHeader__breadcrumbs"><a href="https://www.coffeeproject.ru">Главная</a> / <a href="https://www.coffeeproject.ru/blog/c/o-kofe"> Все о Кофе </a></p>
              <h1 class="bem_imageHeader__header">{$title}</h1>
              <a class="bem_tags__item" href="https://www.coffeeproject.ru/blog/t/dzhezva"> джезва </a>
              <a class="bem_tags__item" href="https://www.coffeeproject.ru/blog/t/turka"> турка </a>
            </div>
            <div id="blogContentLayout"><div class="smart-toc"><a>Содержание <span>показать</span></a></div>{$body}
            <div class='rp4wp-related-posts'><h3>Читайте также</h3></div></div>
            <div class="bem_nextArticle"><a href="/blog/x">Дальше</a></div></body></html>
            HTML;

        File::ensureDirectoryExists("{$site}/blog/how_make_coffee_in_cezve");
        File::put("{$site}/blog/how_make_coffee_in_cezve/index.html", $page('Как заварить кофе в турке на плите', <<<'HTML'
            <p><strong><em>Несколько советов.</em></strong></p><span id="more-742"></span>
            <h2>ШАГ ПЕРВЫЙ.<br> <small>Выбор кофе</small></h2>
            <p>Попробуйте <a href="https://www.coffeeproject.ru/coffee/kenya">кофе из Кении</a>.</p>
            <p><img src="data:image/svg+xml,%3Csvg%3E" data-lazy-src="https://www.coffeeproject.ru/blog/wp-content/uploads/2018/05/turka-600x400.jpg" alt="Турка на плите"></p>
            <p><img src="data:image/svg+xml,%3Csvg%3E" data-lazy-src="https://www.coffeeproject.ru/images/blog/82/never-archived.webp" alt=""></p>
            <figure class="wp-block-embed"><div class="rll-youtube-player" data-src="https://www.youtube.com/embed/qtXsqPOORSM" data-id="qtXsqPOORSM"></div><noscript><iframe src="https://www.youtube.com/embed/qtXsqPOORSM"></iframe></noscript></figure>
            <p>&nbsp;</p>
            HTML));

        File::ensureDirectoryExists("{$site}/blog/zipped_post");
        File::put("{$site}/blog/zipped_post/index.html", gzencode($page('Сжатая статья', '<p>Текст сжатой статьи.</p>')));

        // A WordPress page that is not an article.
        File::ensureDirectoryExists("{$site}/blog/page");
        File::put("{$site}/blog/page/index.html", '<html><body><h1>Блог</h1></body></html>');

        File::ensureDirectoryExists("{$site}/brew/cezve");
        File::put("{$site}/brew/cezve/index.html", <<<'HTML'
            <html><head><title>Джезва. Правильно варим кофе</title><meta name="description" content="Как сварить кофе в джезве"></head><body>
            <div class="container-fluid jumbotron bg_cezve"><div class="container"><p class="mainsubtitle">Джезва, турка, кофе по-восточному</p></div></div>
            <div class="container">
              <div class="col-md-9"><p>Пожалуй, кофе в джезве самый знакомый.</p><h2>Шаг первый</h2><p>Прокалите песок.</p>
                <p><img src="/img/brewguides/cezve/cezve_01.jpg"></p>
                <h4 align="center">Где купить?</h4><p align="center"><a href="http://www.amazon.com/x">Джезва</a></p></div>
              <div class="col-md-12"><h2>Приятного кофепития!</h2><p>Дайте напитку отстояться.</p></div>
              <div class="col-md-12"><h2>Попробуйте приготовить</h2></div>
              <div class="col-md-4"><a class="brewguides" href="/brew/chemex">Кемекс</a></div>
            </div></body></html>
            HTML);
    }

    private function jpeg(string $path, int $width, int $height): void
    {
        File::ensureDirectoryExists(dirname($path));
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 40));
        imagejpeg($image, $path);
    }
}
