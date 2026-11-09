<?php

namespace App\Services\Blog;

use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Turns a post's Markdown into safe HTML for the article page.
 *
 * Raw HTML in the Markdown is stripped and unsafe links are dropped, so text
 * from editors, agents or the old site can never inject scripts. Two embeds
 * are supported, each on a line of its own:
 *
 *     ::youtube[VIDEO_ID]   a YouTube video
 *     ::product[slug]       a product card from the catalog
 */
class BlogMarkdown
{
    private const EMBED = '/^::(youtube|product)\[([A-Za-z0-9_-]{1,100})\][ \t]*$/m';

    private ?MarkdownConverter $converter = null;

    /**
     * Render a post, cached until the post changes. Product cards are filled
     * in on every request so prices and stock stay current.
     *
     * @return array{html: string, toc: list<array{id: string, text: string}>}
     */
    public function forPost(BlogPost $post): array
    {
        $key = "blog.post.{$post->id}.".($post->updated_at?->getTimestamp() ?? 0);
        $rendered = Cache::remember($key, now()->addWeek(), fn () => $this->render($post->body));

        return ['html' => $this->fillProducts($rendered['html']), 'toc' => $rendered['toc']];
    }

    /**
     * Render Markdown to HTML. Product embeds are left as placeholders.
     *
     * @return array{html: string, toc: list<array{id: string, text: string}>}
     */
    public function render(string $markdown): array
    {
        $embeds = [];
        $markdown = preg_replace_callback(self::EMBED, function (array $m) use (&$embeds) {
            $token = 'BLOGEMBED'.count($embeds).'END';
            $embeds[$token] = [$m[1], $m[2]];

            return "\n{$token}\n";
        }, $markdown) ?? $markdown;

        $html = (string) $this->converter()->convert($markdown);

        foreach ($embeds as $token => [$type, $id]) {
            $html = str_replace("<p>{$token}</p>", $type === 'youtube' ? $this->youtube($id) : "<!--blog-product:{$id}-->", $html);
        }

        $html = str_replace('<img ', '<img loading="lazy" decoding="async" ', $html);
        $html = preg_replace('#<table>(.*?)</table>#s', '<div class="table-scroll"><table>$1</table></div>', $html) ?? $html;

        return $this->anchorHeadings($html);
    }

    /**
     * Give h2 and h3 headings ids for links and list the h2s as a table of contents.
     *
     * @return array{html: string, toc: list<array{id: string, text: string}>}
     */
    private function anchorHeadings(string $html): array
    {
        $toc = [];
        $used = [];

        $html = preg_replace_callback('#<h([23])>(.*?)</h\1>#s', function (array $m) use (&$toc, &$used) {
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $base = Str::slug($text, '-', 'ru') ?: 'section';
            $id = $base;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = "{$base}-{$i}";
            }
            $used[$id] = true;

            if ($m[1] === '2') {
                $toc[] = ['id' => $id, 'text' => $text];
            }

            return "<h{$m[1]} id=\"{$id}\">{$m[2]}</h{$m[1]}>";
        }, $html) ?? $html;

        return ['html' => $html, 'toc' => $toc];
    }

    /**
     * Replace product placeholders with product cards, or drop them when the
     * product is no longer on sale.
     */
    private function fillProducts(string $html): string
    {
        if (! preg_match_all('/<!--blog-product:([A-Za-z0-9_-]+)-->/', $html, $matches)) {
            return $html;
        }

        $products = Product::active()
            ->whereIn('slug', array_unique($matches[1]))
            ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])
            ->get()
            ->keyBy('slug');

        return preg_replace_callback('/<!--blog-product:([A-Za-z0-9_-]+)-->/', fn (array $m) => isset($products[$m[1]])
            ? view('shop.blog.product-embed', ['product' => $products[$m[1]]])->render()
            : '', $html) ?? $html;
    }

    private function youtube(string $id): string
    {
        $id = e($id);

        return '<figure class="blog-video">'
            .'<iframe src="https://www.youtube-nocookie.com/embed/'.$id.'" title="'.e(__('YouTube video')).'" loading="lazy" '
            .'allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
            .'<figcaption><a href="https://www.youtube.com/watch?v='.$id.'" rel="noopener" target="_blank">'.e(__('Watch on YouTube')).'</a></figcaption>'
            .'</figure>';
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter) {
            return $this->converter;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => [$host, 'coffeeproject.ru', 'www.coffeeproject.ru'],
                'open_in_new_window' => true,
                'noopener' => 'external',
                'noreferrer' => '',
                'nofollow' => '',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);

        return $this->converter = new MarkdownConverter($environment);
    }
}
