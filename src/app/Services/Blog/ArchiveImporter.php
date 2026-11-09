<?php

namespace App\Services\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\BlogTag;
use App\Models\Redirect;
use App\Services\Catalog\ImageProcessor;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Loads the restore bundle written by ArchiveExtractor into the blog.
 *
 * Every post arrives as a draft with its original date; nothing is published.
 * Running it again updates archive posts nobody has touched since and never
 * overwrites a post an editor or agent has changed.
 */
class ArchiveImporter
{
    public const AUTHOR = 'Coffee Project';

    public const REVISION_AUTHOR = 'import: web archive';

    /** Words that mark dated news: events, holidays, schedules, contests. */
    private const DATED = '/(чемпионат|фестивал|конкурс|розыгрыш|лотере|график работы|праздник|с днем|с днём|новый год|анонс|приглаша|мероприяти|вакансi|вакансия|открыти|выставк|итоги|результаты)/iu';

    /** @var list<array<string, mixed>> */
    private array $report = [];

    public function __construct(private ImageProcessor $images) {}

    /**
     * Import the bundle (or one post, by slug), yielding a line per post.
     *
     * @return Generator<int, string, mixed, array{created: int, updated: int, skipped: int, images: int, report: list<array<string, mixed>>}>
     */
    public function import(string $path, bool $dryRun = false, ?string $only = null): Generator
    {
        $path = rtrim($path, '/');
        $bundle = json_decode((string) @file_get_contents("{$path}/posts.json"), true);

        if (! is_array($bundle) || ! isset($bundle['posts'])) {
            throw new RuntimeException("No readable posts.json in {$path}. Run blog:extract-archive first.");
        }

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'images' => 0];
        $this->report = [];

        if (! $dryRun) {
            $this->categories($bundle['categories'] ?? []);
            $this->redirects($bundle['redirects'] ?? []);
        }

        foreach ($bundle['posts'] as $data) {
            if ($only && $only !== $data['slug']) {
                continue;
            }

            $existing = BlogPost::withTrashed()->where('slug', $data['slug'])->first();
            $action = $this->suggest($data);

            if ($existing && ($existing->edited_at || $existing->source !== 'archive')) {
                $counts['skipped']++;
                $this->addToReport($data, $action, 'kept: edited after import');
                yield "{$data['slug']}: kept, it was edited after the import";

                continue;
            }

            if ($dryRun) {
                $counts[$existing ? 'updated' : 'created']++;
                $this->addToReport($data, $action, $existing ? 'would update' : 'would create');
                yield "{$data['slug']}: would ".($existing ? 'update' : 'create');

                continue;
            }

            try {
                $made = DB::transaction(fn () => $this->save($path, $data, $existing));
            } catch (Throwable $e) {
                $counts['skipped']++;
                $this->addToReport($data, $action, 'failed: '.$e->getMessage());
                yield "{$data['slug']}: FAILED, {$e->getMessage()}";

                continue;
            }

            $counts[$existing ? 'updated' : 'created']++;
            $counts['images'] += $made;
            $this->addToReport($data, $action, $existing ? 'updated' : 'created');
            yield "{$data['slug']}: ".($existing ? 'updated' : 'created');
        }

        return [...$counts, 'report' => $this->report];
    }

    /**
     * Write the report as CSV for people and the editorial agent.
     *
     * @param  list<array<string, mixed>>  $report
     */
    public static function writeReport(array $report, string $file): void
    {
        @mkdir(dirname($file), 0775, true);
        $handle = fopen($file, 'w');
        if ($handle === false) {
            throw new RuntimeException("Cannot write the report to {$file}.");
        }
        fputcsv($handle, array_keys($report[0] ?? ['slug' => '']), escape: '');
        foreach ($report as $row) {
            fputcsv($handle, $row, escape: '');
        }
        fclose($handle);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return int images newly stored
     */
    private function save(string $path, array $data, ?BlogPost $post): int
    {
        $stored = 0;
        $urls = [];

        foreach ($data['images'] as $image) {
            [$model, $new] = $this->image($path, $image['file'], $image['alt'] ?? null);
            $stored += (int) $new;
            $urls["archive-image:{$image['file']}"] = '/storage/'.$model->path;
        }

        $cover = null;
        if ($data['cover']) {
            [$cover, $new] = $this->image($path, $data['cover']['file'], null);
            $stored += (int) $new;
        }

        $body = strtr($data['body'], $urls);
        // Any image reference that could not be stored is dropped rather than left broken.
        $body = preg_replace('/!\[[^\]]*\]\(archive-image:[^)]+\)\n?/', '', $body) ?? $body;

        $published = $data['published_at'] ? Carbon::parse($data['published_at']) : null;

        $attributes = [
            'title' => Str::limit($data['title'], 250, ''),
            'excerpt' => $data['excerpt'],
            'body' => $body,
            'blog_category_id' => $data['category'] ? BlogCategory::where('slug', $data['category'])->value('id') : null,
            'cover_image_id' => $cover?->id,
            'published_at' => $published,
            'author_name' => self::AUTHOR,
            'source' => 'archive',
            'legacy_url' => $data['legacy_url'],
            'import_meta' => [
                'words' => $data['words'],
                'images_found' => count($data['images']) + ($data['cover'] ? 1 : 0),
                'images_missing' => count($data['missing_images']),
                'missing_images' => $data['missing_images'],
                'old_category' => $data['old_category'],
                'old_tags' => array_column($data['tags'], 'name'),
                'links' => $data['links'],
                'modified_at' => $data['modified_at'],
                'suggested_action' => $this->suggest($data),
            ],
        ];

        if ($post) {
            if ($post->trashed()) {
                $post->restore();
            }
            $post->fill($attributes)->save();
        } else {
            $post = BlogPost::create([...$attributes, 'slug' => $data['slug'], 'status' => BlogPostStatus::Draft]);
        }

        $tagIds = [];
        foreach ($data['tags'] as $tag) {
            $tagIds[] = BlogTag::firstOrCreate(
                ['slug' => Str::limit($tag['slug'], 250, '')],
                ['name' => Str::limit($tag['name'] ?: $tag['slug'], 250, '')],
            )->id;
        }
        $post->tags()->sync($tagIds);

        // A revision for the first import and for re-imports that changed the text.
        if ($post->wasRecentlyCreated || $post->wasChanged(BlogPostRevision::FIELDS)) {
            $post->revisions()->create([
                'author' => self::REVISION_AUTHOR,
                'note' => $post->wasRecentlyCreated ? 'Imported from the web archive' : 'Re-imported from the web archive',
                ...$post->only(BlogPostRevision::FIELDS),
            ]);
        }

        return $stored;
    }

    /**
     * Store an archived image as WebP once, however many posts use it.
     *
     * @return array{0: BlogImage, 1: bool} the image and whether it was new
     */
    private function image(string $path, string $file, ?string $alt): array
    {
        $existing = BlogImage::where('original_name', $file)->first();
        if ($existing) {
            if ($alt && ! $existing->alt) {
                $existing->update(['alt' => $alt]);
            }

            return [$existing, false];
        }

        $source = "{$path}/images/".basename($file);
        if (! is_file($source)) {
            throw new RuntimeException("Image {$file} is missing from the bundle.");
        }

        $sizes = $this->images->storeSizes($source, 'blog/archive/'.pathinfo($file, PATHINFO_FILENAME), BlogImage::LARGE_WIDTH, BlogImage::THUMB_WIDTH);

        return [BlogImage::create([...$sizes, 'original_name' => $file, 'alt' => $alt ?: null, 'uploaded_by' => self::REVISION_AUTHOR]), true];
    }

    /**
     * @param  list<array{slug: string, name: string, description: string}>  $categories
     */
    private function categories(array $categories): void
    {
        foreach ($categories as $sort => $category) {
            BlogCategory::firstOrCreate(['slug' => $category['slug']], [
                'name' => $category['name'],
                'description' => $category['description'],
                'sort' => $sort,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $redirects
     */
    private function redirects(array $redirects): void
    {
        foreach ($redirects as $from => $to) {
            Redirect::firstOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301]);
        }
    }

    /**
     * A first guess for the editorial agent: publish, review or redirect.
     *
     * @param  array<string, mixed>  $data
     */
    private function suggest(array $data): string
    {
        $found = count($data['images']);
        $missing = count($data['missing_images']);

        return match (true) {
            $data['words'] < 150 => 'redirect',
            $data['category'] === 'kofeinye-novosti' && (bool) preg_match(self::DATED, $data['title'].' '.$data['excerpt']) => 'redirect',
            (bool) preg_match(self::DATED, $data['title']) => 'redirect',
            $missing > 2 && $missing > $found => 'review',
            default => 'publish',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addToReport(array $data, string $action, string $result): void
    {
        $this->report[] = [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'date' => substr((string) $data['published_at'], 0, 10),
            'words' => $data['words'],
            'images_found' => count($data['images']) + ($data['cover'] ? 1 : 0),
            'images_missing' => count($data['missing_images']),
            'old_category' => $data['old_category']['slug'] ?? '',
            'category' => $data['category'] ?? '',
            'suggested_action' => $action,
            'result' => $result,
        ];
    }
}
