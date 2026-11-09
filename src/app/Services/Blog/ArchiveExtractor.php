<?php

namespace App\Services\Blog;

use Dom\Element;
use Dom\HTMLDocument;
use Generator;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use RuntimeException;

/**
 * Reads the old blog out of the Wayback Machine copy of coffeeproject.ru and
 * writes a restore bundle: posts.json (articles as Markdown with their facts)
 * and images/ (every image that was archived).
 *
 * The archive is untrusted data: pages are only parsed, never executed, and
 * nothing outside the archive folder is read.
 */
class ArchiveExtractor
{
    /** Old WordPress categories => new blog sections. */
    public const CATEGORY_MAP = [
        'o-kofe' => 'o-kofe',
        'kofevarki' => 'zavarivanie',
        'recepty-kofe' => 'recepty-kofe',
        'polza-kofe' => 'polza-kofe',
        'kfoe-profi' => 'kofe-profi',
        'i-am-barista' => 'kofe-profi',
        'kofeinye-novosti' => 'kofeinye-novosti',
        'kafe-i-restorany' => 'kofeinye-novosti',
    ];

    /** The new blog sections, in menu order. */
    public const CATEGORIES = [
        'zavarivanie' => ['Как заваривать', 'Пошаговые рецепты для турки, пуровера, аэропресса, френч-пресса и других способов, а также кофемолки и аксессуары.'],
        'recepty-kofe' => ['Рецепты', 'Кофейные напитки и десерты, которые легко повторить дома.'],
        'o-kofe' => ['О кофе', 'Откуда берётся кофе: страны, сорта, обработка, обжарка и вкус.'],
        'polza-kofe' => ['Кофе и здоровье', 'Что известно о пользе и вреде кофе, кофеине и декафе.'],
        'kofe-profi' => ['Для профи', 'Для бариста и тех, кто хочет разбираться в кофе глубже: профессия, чемпионаты, оборудование.'],
        'kofeinye-novosti' => ['Новости и события', 'Новости кофейного мира и события, о которых мы писали.'],
    ];

    /** Old section addresses that redirect to the new sections. */
    public const SECTION_REDIRECTS = [
        '/blog/c/kofevarki' => '/blog/c/zavarivanie',
        '/blog/c/kfoe-profi' => '/blog/c/kofe-profi',
        '/blog/c/i-am-barista' => '/blog/c/kofe-profi',
        '/blog/c/kafe-i-restorany' => '/blog/c/kofeinye-novosti',
        '/blog/topics/coffee-at-home' => '/blog/c/zavarivanie',
        '/blog/topics/coffee-edu' => '/blog/c/o-kofe',
        '/blog/topics/become-barista' => '/blog/c/kofe-profi',
        '/blog/topics/coffee-abroad' => '/blog/c/kofeinye-novosti',
        '/blog/topics/cp-life' => '/blog/c/kofeinye-novosti',
        '/blog/topics/workshops' => '/blog/c/kofeinye-novosti',
    ];

    /** Brewing guides from /brew/{name} => new article slug. */
    public const BREW_GUIDES = [
        'aero' => 'aeropress-recept',
        'cezve' => 'kofe-v-dzhezve',
        'chemex' => 'kofe-v-kemekse',
        'drip' => 'kapelnaya-kofevarka',
        'french' => 'kofe-vo-french-presse',
        'percolator' => 'geyzernaya-kofevarka',
        'siphon' => 'kofe-v-sifone',
        'v60' => 'pourover-v60',
        'vietnam' => 'kofe-po-vetnamski',
        'christmas_raf' => 'rozhdestvenskiy-raf',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private string $archive;

    private string $out;

    /** @var array<string, string>|null archived URL (no scheme, no www) => local file */
    private ?array $manifest = null;

    /** @var array<string, string> manifest URL => earliest capture timestamp */
    private array $captured = [];

    /**
     * Extract every post (or one, by slug) into the bundle folder, yielding a
     * progress line per post.
     *
     * @return Generator<int, string, mixed, array{posts: int, images: int, missing: int}>
     */
    public function extract(string $archive, string $out, ?string $only = null): Generator
    {
        $this->archive = rtrim($archive, '/');
        $this->out = rtrim($out, '/');

        if (! is_dir("{$this->archive}/site/blog")) {
            throw new RuntimeException("No site/blog folder in {$this->archive}. Pass the archive's backups folder.");
        }

        @mkdir("{$this->out}/images", 0775, true);

        $posts = [];
        $folders = glob("{$this->archive}/site/blog/*", GLOB_ONLYDIR) ?: [];
        sort($folders);

        foreach ($folders as $folder) {
            $slug = basename($folder);
            if ($only && $only !== $slug) {
                continue;
            }

            $html = $this->read("{$folder}/index.html");
            if ($html === null || ! str_contains($html, 'bem_imageHeader') || ! str_contains($html, 'bem_nextArticle')) {
                continue;
            }

            $post = $this->post($slug, $html);
            $posts[] = $post;
            yield sprintf('%s: %d words, %d images, %d missing', $slug, $post['words'], count($post['images']), count($post['missing_images']));
        }

        foreach (self::BREW_GUIDES as $name => $slug) {
            if ($only && $only !== $slug) {
                continue;
            }

            $html = $this->read("{$this->archive}/site/brew/{$name}/index.html");
            if ($html === null) {
                yield "{$slug}: /brew/{$name} is not in the archive";

                continue;
            }

            $post = $this->brewGuide($name, $slug, $html);
            $posts[] = $post;
            yield sprintf('%s (from /brew/%s): %d words, %d images, %d missing', $slug, $name, $post['words'], count($post['images']), count($post['missing_images']));
        }

        $bundle = [
            'extracted_at' => now()->toAtomString(),
            'categories' => collect(self::CATEGORIES)->map(fn (array $c, string $slug) => ['slug' => $slug, 'name' => $c[0], 'description' => $c[1]])->values()->all(),
            'redirects' => self::SECTION_REDIRECTS,
            'posts' => $posts,
        ];

        file_put_contents("{$this->out}/posts.json", json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'posts' => count($posts),
            'images' => count(glob("{$this->out}/images/*") ?: []),
            'missing' => array_sum(array_map(fn (array $p) => count($p['missing_images']), $posts)),
        ];
    }

    /**
     * A WordPress article page.
     *
     * @return array<string, mixed>
     */
    private function post(string $slug, string $html): array
    {
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

        $title = $this->text($doc->querySelector('h1.bem_imageHeader__header'))
            ?: preg_replace('/\s+[|—-]\s+.*$/u', '', $this->meta($doc, 'og:title') ?? $slug);

        $published = $this->meta($doc, 'article:published_time')
            ?? (preg_match('/"datePublished"\s*:\s*"([^"]+)"/', $html, $m) ? $m[1] : null);
        if (! $published && preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $this->text($doc->querySelector('.bem_blogHeader__subtitle')), $m)) {
            $published = "{$m[3]}-{$m[2]}-{$m[1]}T12:00:00+05:00";
        }

        $oldCategory = null;
        foreach ($doc->querySelectorAll('.bem_imageHeader__breadcrumbs a') as $link) {
            if (preg_match('#/blog/c/([^/?"]+)#', (string) $link->getAttribute('href'), $m)) {
                $oldCategory = ['slug' => $m[1], 'name' => $this->text($link)];
            }
        }

        $tags = [];
        foreach ($doc->querySelectorAll('a.bem_tags__item') as $link) {
            if (preg_match('#/blog/t/([^/?"]+)#', (string) $link->getAttribute('href'), $m)) {
                $tags[$m[1]] = ['slug' => urldecode($m[1]), 'name' => $this->text($link)];
            }
        }

        $cover = null;
        $header = $doc->querySelector('.bem_imageHeader');
        if ($header && preg_match('/url\(\s*[\'"]?([^\'")]+)/', (string) $header->getAttribute('style'), $m)) {
            $cover = $m[1];
        }

        $body = $doc->querySelector('#blogContentLayout');
        $state = ['images' => [], 'missing' => [], 'links' => []];
        $markdown = $body ? $this->markdown($doc, $body, $state) : '';

        $coverImage = null;
        if ($cover && ! str_contains($cover, 'data:')) {
            $file = $this->image($cover);
            $file ? $coverImage = ['file' => $file, 'source' => $cover] : $state['missing'][] = $cover;
        }

        return [
            'slug' => $slug,
            'title' => trim(html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5)),
            'excerpt' => $this->excerpt($this->meta($doc, 'og:description') ?? $this->meta($doc, 'description')),
            'published_at' => $published,
            'modified_at' => $this->meta($doc, 'article:modified_time'),
            'legacy_url' => "/blog/{$slug}",
            'old_category' => $oldCategory,
            'category' => $oldCategory ? (self::CATEGORY_MAP[$oldCategory['slug']] ?? null) : null,
            'tags' => array_values($tags),
            'cover' => $coverImage,
            'body' => $markdown,
            'words' => $this->words($markdown),
            'images' => $state['images'],
            'missing_images' => array_values(array_unique($state['missing'])),
            'links' => array_values(array_unique($state['links'])),
        ];
    }

    /**
     * A 2015 brewing guide from /brew/{name}.
     *
     * @return array<string, mixed>
     */
    private function brewGuide(string $name, string $slug, string $html): array
    {
        $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');

        $title = $this->text($doc->querySelector('.mainsubtitle'))
            ?: preg_replace('/\.\s.*$/u', '', $this->text($doc->querySelector('title')));

        // The article is the column after the photo header; "Try making…" links and the shop blocks are dropped.
        $root = $doc->createElement('div');
        foreach ($doc->querySelectorAll('.jumbotron ~ .container > div') as $column) {
            if (str_contains($this->text($column), 'Попробуйте приготовить') && ! $column->querySelector('p')) {
                break;
            }
            $root->append($column->cloneNode(true));
        }
        foreach ($root->querySelectorAll('h4, hr, p[align="center"], .likely, a.brewguides') as $node) {
            $node->remove();
        }

        $state = ['images' => [], 'missing' => [], 'links' => []];
        $markdown = $this->markdown($doc, $root, $state);
        $first = $state['images'][0] ?? null;

        return [
            'slug' => $slug,
            'title' => trim((string) $title),
            'excerpt' => $this->excerpt($this->meta($doc, 'description')),
            'published_at' => $this->capturedAt("coffeeproject.ru/brew/{$name}") ?? '2016-01-01T12:00:00+05:00',
            'modified_at' => null,
            'legacy_url' => "/brew/{$name}",
            'old_category' => ['slug' => 'brew', 'name' => 'Варим кофе'],
            'category' => 'zavarivanie',
            'tags' => [],
            'cover' => $first ? ['file' => $first['file'], 'source' => $first['source']] : null,
            'body' => $markdown,
            'words' => $this->words($markdown),
            'images' => $state['images'],
            'missing_images' => array_values(array_unique($state['missing'])),
            'links' => array_values(array_unique($state['links'])),
        ];
    }

    /**
     * Clean an article's HTML and convert it to Markdown. Images are copied
     * into the bundle and referenced as archive-image:{file}; missing ones are
     * removed and recorded.
     *
     * @param  array{images: list<array{file: string, alt: string, source: string}>, missing: list<string>, links: list<string>}  $state
     */
    private function markdown(HTMLDocument $doc, Element $root, array &$state): string
    {
        $embeds = [];

        // YouTube players: keep the video id as an embed line.
        foreach ($root->querySelectorAll('.rll-youtube-player[data-id]') as $player) {
            $token = 'ARCHIVEEMBED'.count($embeds).'END';
            $embeds[$token] = '::youtube['.$player->getAttribute('data-id').']';
            $this->replaceWithText($doc, $player->closest('figure') ?? $player, $token);
        }

        foreach ($root->querySelectorAll('script, style, noscript, form, button, svg, .smart-toc, .rp4wp-related-posts, .bem_socialSidebar, span[id^="more-"]') as $node) {
            $node->remove();
        }

        foreach ($root->querySelectorAll('iframe') as $frame) {
            $src = html_entity_decode((string) ($frame->getAttribute('data-lazy-src') ?: $frame->getAttribute('src')));
            $token = 'ARCHIVEEMBED'.count($embeds).'END';
            if (preg_match('#youtu(?:be(?:-nocookie)?\.com/embed/|\.be/)([A-Za-z0-9_-]{6,20})#', $src, $m)) {
                $embeds[$token] = "::youtube[{$m[1]}]";
            } elseif (preg_match('#^(https?:)?//(player\.vimeo\.com|vk\.com|livestream\.com|w\.soundcloud\.com)#', $src)) {
                $embeds[$token] = '[Смотреть видео]('.(str_starts_with($src, '//') ? 'https:'.$src : $src).')';
            } else {
                $frame->remove();

                continue;
            }
            $this->replaceWithText($doc, $frame->closest('figure') ?? $frame, $token);
        }

        foreach ($root->querySelectorAll('img') as $img) {
            $src = html_entity_decode((string) ($img->getAttribute('data-lazy-src') ?: $img->getAttribute('src')));
            $link = $img->parentElement?->tagName === 'A' ? $img->parentElement : null;

            // A thumbnail linked to its full-size file: use the bigger one.
            if ($link && preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $link->getAttribute('href'))) {
                $big = html_entity_decode((string) $link->getAttribute('href'));
                $src = $this->image($big) ? $big : $src;
                $link->replaceWith($img);
            }

            $file = ($src && ! str_starts_with($src, 'data:')) ? $this->image($src) : null;
            if ($file) {
                $alt = trim((string) $img->getAttribute('alt'));
                $state['images'][] = ['file' => $file, 'alt' => $alt, 'source' => $src];
                foreach ($img->getAttributeNames() as $attr) {
                    $img->removeAttribute($attr);
                }
                $img->setAttribute('src', "archive-image:{$file}");
                $img->setAttribute('alt', $alt);
            } else {
                if ($src && ! str_starts_with($src, 'data:')) {
                    $state['missing'][] = $src;
                }
                $figure = $img->closest('figure');
                ($figure && count($figure->querySelectorAll('img')) === 1) ? $figure->remove() : $img->remove();
            }
        }

        // Headings: no h1 inside the article, no line breaks inside headings.
        foreach ($root->querySelectorAll('h1, h2, h3, h4, h5, h6') as $heading) {
            foreach ($heading->querySelectorAll('br') as $br) {
                $br->replaceWith($doc->createTextNode(' '));
            }
            if ($heading->tagName === 'H1') {
                $h2 = $doc->createElement('h2');
                $h2->append(...iterator_to_array($heading->childNodes));
                $heading->replaceWith($h2);
            }
        }

        // Links to the old site become site-relative; external ones are recorded.
        foreach ($root->querySelectorAll('a[href]') as $link) {
            $href = html_entity_decode(trim((string) $link->getAttribute('href')));
            if (preg_match('#^(?:https?:)?//(?:www\.)?coffeeproject\.ru(?::80)?(/[^\s]*)?$#i', $href, $m)) {
                $link->setAttribute('href', ($m[1] ?? '') ?: '/');
            } elseif (preg_match('#^https?://#i', $href)) {
                $state['links'][] = $href;
            }
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style',
            'header_style' => 'atx',
            'hard_break' => true,
            'strip_placeholder_links' => true,
        ]);
        $converter->getEnvironment()->addConverter(new TableConverter);

        $markdown = $converter->convert($root->innerHTML);
        $markdown = strtr($markdown, $embeds);
        $markdown = str_replace(["\u{A0}", '&nbsp;'], ' ', $markdown);
        $markdown = preg_replace('/^[ \t]+$/m', '', $markdown) ?? $markdown;
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }

    private function replaceWithText(HTMLDocument $doc, Element $node, string $text): void
    {
        $p = $doc->createElement('p');
        $p->textContent = $text;
        $node->replaceWith($p);
    }

    /**
     * Find an archived copy of an image URL and copy it into the bundle.
     * Returns the bundle file name, or null when the image was never archived.
     */
    private function image(string $url): ?string
    {
        $local = $this->locate($url);
        if (! $local) {
            return null;
        }

        $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (! in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            $info = @getimagesize($local);
            $ext = match ($info[2] ?? null) {
                IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', default => null,
            };
            if (! $ext) {
                return null;
            }
        }

        $file = substr(sha1($local), 0, 16).'.'.$ext;
        $target = "{$this->out}/images/{$file}";
        if (! is_file($target)) {
            copy($local, $target);
        }

        return $file;
    }

    /**
     * Map an image URL to a file in the archive, falling back to the largest
     * archived WordPress size of the same upload.
     */
    private function locate(string $url): ?string
    {
        $key = $this->key($url);
        $manifest = $this->manifest();
        $isUpload = str_contains($key, '/wp-content/uploads/');

        // WordPress uploads always go through the size search below to find the biggest copy.
        if (! $isUpload && isset($manifest[$key]) && is_file($manifest[$key])) {
            return $manifest[$key];
        }

        if (! preg_match('#^coffeeproject\.ru(/.*)$#', $key, $m)) {
            return null;
        }

        $path = $this->safePath($m[1]);
        if ($path === null) {
            return null;
        }

        if (! $isUpload) {
            return is_file("{$this->archive}/site{$path}") ? "{$this->archive}/site{$path}" : null;
        }

        // WordPress sizes: name-800x400.jpg and name.jpg are the same upload.
        $base = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $path) ?? $path;
        $dir = "{$this->archive}/site".dirname($base);
        $stem = pathinfo($base, PATHINFO_FILENAME);
        $ext = pathinfo($base, PATHINFO_EXTENSION);

        $best = null;
        $bestArea = -1;
        foreach (glob($dir.'/'.$this->globEscape($stem).'*.'.$this->globEscape($ext)) ?: [] as $candidate) {
            $name = pathinfo($candidate, PATHINFO_FILENAME);
            if ($name === $stem) {
                return $candidate;
            }
            if (preg_match('/^'.preg_quote($stem, '/').'-(\d+)x(\d+)$/', $name, $s) && (int) $s[1] * (int) $s[2] > $bestArea) {
                $best = $candidate;
                $bestArea = (int) $s[1] * (int) $s[2];
            }
        }

        return $best ?? (isset($manifest[$key]) && is_file($manifest[$key]) ? $manifest[$key] : null);
    }

    /**
     * @return array<string, string>
     */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $this->manifest = [];
        $handle = @fopen("{$this->archive}/_index/manifest.csv", 'r');
        if (! $handle) {
            return $this->manifest;
        }

        $header = fgetcsv($handle, escape: '');
        if (! is_array($header)) {
            fclose($handle);

            return $this->manifest;
        }
        $header = array_map('strval', $header);

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $row = array_combine($header, array_pad($row, count($header), ''));
            $key = $this->key($row['original_url']);
            if (! isset($this->captured[$key]) || $row['timestamp'] < $this->captured[$key]) {
                $this->captured[$key] = $row['timestamp'];
            }
            if ($row['result'] === 'saved' && $row['local_path'] && ($local = $this->safePath('/'.$row['local_path']))) {
                $this->manifest[$key] = $this->archive.$local;
            }
        }
        fclose($handle);

        return $this->manifest;
    }

    /**
     * When the Wayback Machine first saw an address, as an ISO date.
     */
    private function capturedAt(string $key): ?string
    {
        $this->manifest();
        $ts = $this->captured[$key] ?? null;

        return $ts && preg_match('/^(\d{4})(\d{2})(\d{2})/', $ts, $m) ? "{$m[1]}-{$m[2]}-{$m[3]}T12:00:00+05:00" : null;
    }

    /**
     * A URL without scheme, "www." and port, percent-decoded.
     */
    private function key(string $url): string
    {
        $url = html_entity_decode(trim($url));
        $url = preg_replace('#^(?:https?:)?//#i', '', $url) ?? $url;
        $url = preg_replace('#^www\.#i', '', $url) ?? $url;
        $url = preg_replace('#^([^/]+):\d+#', '$1', $url) ?? $url;
        if (str_starts_with($url, '/')) {
            $url = 'coffeeproject.ru'.$url;
        }
        $url = preg_replace('#(?<!:)/{2,}#', '/', rawurldecode($url)) ?? $url;

        return strtok($url, '?#') ?: $url;
    }

    /**
     * A path that cannot climb out of the archive folder.
     */
    private function safePath(string $path): ?string
    {
        return str_contains($path, '..') || str_contains($path, "\0") ? null : $path;
    }

    private function globEscape(string $value): string
    {
        return preg_replace('/([*?\[\]])/', '[$1]', $value) ?? $value;
    }

    /**
     * Read an archived page, which may be gzip-compressed despite its name.
     */
    private function read(string $path): ?string
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        if (str_starts_with($data, "\x1f\x8b")) {
            $data = @gzdecode($data);
        }

        return $data === false ? null : $data;
    }

    private function meta(HTMLDocument $doc, string $name): ?string
    {
        foreach ($doc->querySelectorAll('meta') as $meta) {
            if ($meta->getAttribute('property') === $name || $meta->getAttribute('name') === $name) {
                $content = trim((string) $meta->getAttribute('content'));

                return $content === '' ? null : $content;
            }
        }

        return null;
    }

    private function text(?Element $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $element?->textContent) ?? '');
    }

    private function excerpt(?string $text): ?string
    {
        $text = trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/\s*\[(…|&hellip;|\.\.\.)\]\s*$/u', '…', $text) ?? $text;

        return $text === '' ? null : $text;
    }

    private function words(string $markdown): int
    {
        $text = preg_replace(['/^::\w+\[[^\]]*\]\s*$/m', '/!\[[^\]]*\]\([^)]*\)/u', '/\]\([^)]*\)/u', '/[#*_>`|\[\]-]+/u'], ' ', $markdown);

        return count(preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
