<?php

namespace App\Http\Resources\Blog;

use App\Models\BlogPost;
use App\Services\Blog\BlogMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A blog post for the API. Lists show the summary; one post also carries the
 * Markdown body, the rendered HTML, the images it uses and import facts.
 *
 * @mixin BlogPost
 */
class PostResource extends JsonResource
{
    private bool $full = false;

    public function full(): static
    {
        $this->full = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = [
            'id' => $this->id,
            'slug' => $this->slug,
            'url' => url($this->path()),
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'status' => $this->status->value,
            'is_visible' => $this->isVisible(),
            'published_at' => $this->published_at?->toAtomString(),
            'edited_at' => $this->edited_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
            'category' => $this->category?->slug,
            'tags' => $this->tags->pluck('name')->values(),
            'source' => $this->source,
            'words' => $this->wordCount(),
            'reading_minutes' => $this->reading_minutes,
            'cover' => $this->cover ? ImageResource::make($this->cover) : null,
            'legacy_url' => $this->legacy_url,
            'images_missing' => $this->import_meta['images_missing'] ?? 0,
            'triage' => [
                'decision' => $this->triage_decision,
                'confidence' => $this->triage_confidence,
                'reason' => $this->triage_reason,
                'merge_into' => $this->triage_merge_into,
                'triaged_at' => $this->triaged_at?->toAtomString(),
            ],
            'deleted_at' => $this->deleted_at?->toAtomString(),
        ];

        if (! $this->full) {
            return $summary;
        }

        return [
            ...$summary,
            'body' => $this->body,
            'html' => app(BlogMarkdown::class)->forPost($this->resource)['html'],
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'canonical_url' => $this->canonical_url,
            'noindex' => $this->noindex,
            'author_name' => $this->author_name,
            'products' => $this->products->pluck('slug')->values(),
            'images' => ImageResource::collection($this->images),
            'revisions_count' => $this->revisions()->count(),
            'import' => $this->import_meta,
        ];
    }
}
