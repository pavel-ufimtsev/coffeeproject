<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Carbon\CarbonInterface;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A blog article at /blog/{slug}, written in Markdown.
 *
 * @property int $id
 * @property int|null $blog_category_id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property int|null $cover_image_id
 * @property BlogPostStatus $status
 * @property CarbonInterface|null $published_at
 * @property string|null $author_name
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $canonical_url
 * @property bool $noindex
 * @property int $reading_minutes
 * @property string $source
 * @property string|null $legacy_url
 * @property array<string, mixed>|null $import_meta
 * @property CarbonInterface|null $edited_at
 * @property string|null $triage_decision
 * @property int|null $triage_confidence
 * @property string|null $triage_reason
 * @property string|null $triage_merge_into
 * @property CarbonInterface|null $triaged_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'blog_category_id', 'slug', 'title', 'excerpt', 'body', 'cover_image_id', 'status', 'published_at',
    'author_name', 'seo_title', 'seo_description', 'canonical_url', 'noindex', 'source', 'legacy_url',
    'import_meta', 'edited_at', 'triage_decision', 'triage_confidence', 'triage_reason', 'triage_merge_into',
    'triaged_at',
])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, SoftDeletes;

    public const SOURCES = ['archive', 'admin', 'agent'];

    public const TRIAGE_DECISIONS = ['publish', 'refresh', 'merge', 'retire'];

    /** Reading speed used for the "N min read" estimate, in words a minute. */
    private const WORDS_PER_MINUTE = 180;

    /**
     * Keep the reading time up to date and record which images the post uses.
     */
    protected static function booted(): void
    {
        static::saving(function (BlogPost $post) {
            $post->reading_minutes = max(1, (int) ceil($post->wordCount() / self::WORDS_PER_MINUTE));
        });

        static::saved(fn (BlogPost $post) => $post->syncImageUsage());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
            'noindex' => 'boolean',
            'import_meta' => 'array',
            'edited_at' => 'datetime',
            'triaged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * @return BelongsTo<BlogImage, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(BlogImage::class, 'cover_image_id');
    }

    /**
     * @return BelongsToMany<BlogTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    /**
     * Products shown as cards under the article.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'blog_post_product')->withPivot('sort')->orderByPivot('sort');
    }

    /**
     * @return BelongsToMany<BlogImage, $this>
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(BlogImage::class, 'blog_image_post');
    }

    /**
     * @return HasMany<BlogPostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(BlogPostRevision::class)->orderByDesc('id');
    }

    /**
     * Posts readers can see: published, or scheduled with their date reached.
     *
     * @param  Builder<BlogPost>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereIn('status', [BlogPostStatus::Published, BlogPostStatus::Scheduled])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isVisible(): bool
    {
        return in_array($this->status, [BlogPostStatus::Published, BlogPostStatus::Scheduled], true)
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function path(): string
    {
        return "/blog/{$this->slug}";
    }

    /**
     * The date the article was last changed, if that was after it went live.
     */
    public function updatedAfterPublishing(): ?CarbonInterface
    {
        $edited = $this->edited_at;

        return $edited && $this->published_at && $edited->gt($this->published_at->copy()->addDay()) ? $edited : null;
    }

    /**
     * Count the words of the article text, ignoring Markdown syntax.
     */
    public function wordCount(): int
    {
        $text = preg_replace([
            '/^::\w+\[[^\]]*\]\s*$/m', // embeds
            '/!\[[^\]]*\]\([^)]*\)/u',   // images
            '/\]\([^)]*\)/u',            // link targets
            '/[#*_>`|\[\]-]+/u',         // syntax characters
        ], ' ', (string) $this->body);

        return count(preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Link the post to the blog images its cover and body use.
     */
    public function syncImageUsage(): void
    {
        preg_match_all('#blog/[A-Za-z0-9_./-]+\.webp#', (string) $this->body, $matches);

        $ids = BlogImage::whereIn('path', array_unique($matches[0]))->pluck('id');

        if ($this->cover_image_id) {
            $ids->push($this->cover_image_id);
        }

        $this->images()->sync($ids->unique()->values()->all());
    }
}
