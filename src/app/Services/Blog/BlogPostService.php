<?php

namespace App\Services\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\BlogTag;
use App\Models\Product;
use App\Models\Redirect;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Every change to a blog post goes through here, from the admin and from the
 * API alike, so revisions, redirects and slug rules are the same whoever
 * makes the change.
 */
class BlogPostService
{
    /** Fields whose change counts as editing the article. */
    private const CONTENT_FIELDS = ['slug', 'title', 'excerpt', 'body', 'cover_image_id', 'seo_title', 'seo_description'];

    private const TRIAGE_FIELDS = ['triage_decision', 'triage_confidence', 'triage_reason', 'triage_merge_into'];

    private const PLAIN_FIELDS = [
        'slug', 'title', 'excerpt', 'body', 'cover_image_id', 'seo_title', 'seo_description', 'canonical_url',
        'noindex', 'author_name', ...self::TRIAGE_FIELDS,
    ];

    /**
     * Create a post. It starts as a draft unless a status is given.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, BlogActor $actor, ?string $note = null): BlogPost
    {
        return DB::transaction(function () use ($data, $actor, $note) {
            $post = new BlogPost([
                'status' => BlogPostStatus::Draft,
                'source' => $actor->isAgent ? 'agent' : 'admin',
                'author_name' => config('shop.name'),
            ]);
            $post->slug = $data['slug'] ?? $this->uniqueSlug((string) $data['title']);

            return $this->apply($post, $data, $actor, $note ?? 'Created');
        });
    }

    /**
     * Change any fields of a post, including its status.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BlogPost $post, array $data, BlogActor $actor, ?string $note = null): BlogPost
    {
        return DB::transaction(fn () => $this->apply($post, $data, $actor, $note));
    }

    /**
     * Put a post on the site. A future date schedules it instead.
     */
    public function publish(BlogPost $post, BlogActor $actor, ?CarbonInterface $at = null): BlogPost
    {
        return $this->update($post, ['status' => BlogPostStatus::Published->value, ...($at ? ['published_at' => $at] : [])], $actor);
    }

    /**
     * Take a post off the site; it becomes a draft again.
     */
    public function unpublish(BlogPost $post, BlogActor $actor): BlogPost
    {
        return $this->update($post, ['status' => BlogPostStatus::Draft->value], $actor);
    }

    /**
     * Retire a post: off the site, its address redirecting to a better page.
     */
    public function archive(BlogPost $post, BlogActor $actor, ?string $redirectTo = null, ?string $note = null): BlogPost
    {
        return $this->update($post, ['status' => BlogPostStatus::Archived->value, 'redirect_to' => $redirectTo], $actor, $note);
    }

    /**
     * Move a post to the bin, optionally redirecting its address.
     */
    public function delete(BlogPost $post, BlogActor $actor, ?string $redirectTo = null): void
    {
        DB::transaction(function () use ($post, $actor, $redirectTo) {
            if ($redirectTo) {
                $this->redirectAway($post, $redirectTo);
            }
            $post->delete();
            $this->log($actor, 'deleted', $post, $redirectTo ? "redirect to {$redirectTo}" : null);
        });
    }

    /**
     * Bring back the text of an earlier revision as a new revision.
     */
    public function restoreRevision(BlogPost $post, BlogPostRevision $revision, BlogActor $actor): BlogPost
    {
        return $this->update($post, $revision->only(BlogPostRevision::FIELDS), $actor, "Restored revision #{$revision->id}");
    }

    /**
     * A slug from a title that no other post uses.
     */
    public function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title, '-', 'ru') ?: 'post', 120, '');
        $slug = $base;
        for ($i = 2; BlogPost::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function apply(BlogPost $post, array $data, BlogActor $actor, ?string $note): BlogPost
    {
        $oldPath = $post->exists ? $post->path() : null;
        $wasVisible = $post->exists && $post->isVisible();
        $oldStatus = $post->exists ? $post->status : null;

        $post->fill(Arr::only($data, self::PLAIN_FIELDS));

        if (array_key_exists('category', $data)) {
            $post->blog_category_id = $data['category'] ? BlogCategory::where('slug', $data['category'])->value('id') : null;
        }
        if (array_key_exists('published_at', $data)) {
            $post->published_at = $data['published_at'] ? Date::parse($data['published_at']) : null;
        }
        if (array_intersect(array_keys($data), self::TRIAGE_FIELDS)) {
            $post->triaged_at = now();
        }

        $status = isset($data['status']) ? BlogPostStatus::from($data['status']) : $post->status;
        if (in_array($status, [BlogPostStatus::Published, BlogPostStatus::Scheduled], true)) {
            $post->published_at ??= now();
            $status = $post->published_at->isFuture() ? BlogPostStatus::Scheduled : BlogPostStatus::Published;
        }
        $post->status = $status;

        if ($post->exists && $post->isDirty(self::CONTENT_FIELDS)) {
            $post->edited_at = now();
        }

        $contentChanged = ! $post->exists || $post->isDirty(BlogPostRevision::FIELDS);
        $post->save();

        if (array_key_exists('tags', $data)) {
            $post->tags()->sync($this->tagIds((array) $data['tags']));
        }
        if (array_key_exists('products', $data)) {
            $ids = Product::whereIn('slug', (array) $data['products'])->pluck('id', 'slug');
            $post->products()->sync(collect((array) $data['products'])->filter(fn ($slug) => isset($ids[$slug]))
                ->values()->mapWithKeys(fn ($slug, $i) => [$ids[$slug] => ['sort' => $i]])->all());
        }

        // Addresses: a moved published post keeps its old address working.
        if ($oldPath && $oldPath !== $post->path() && $wasVisible) {
            Redirect::moved($oldPath, $post->path());
        }

        if ($post->isVisible() || $post->status === BlogPostStatus::Scheduled) {
            Redirect::release($post->path());
            // Restored pages from elsewhere on the old site (e.g. /brew/cezve) now lead here.
            if ($post->legacy_url && $post->legacy_url !== $post->path()) {
                Redirect::moved($post->legacy_url, $post->path());
            }
        }

        if ($status === BlogPostStatus::Archived && ! empty($data['redirect_to'])) {
            $this->redirectAway($post, (string) $data['redirect_to']);
        }

        if ($contentChanged) {
            $post->revisions()->create([
                'user_id' => $actor->userId,
                'author' => $actor->name,
                'note' => $note ? Str::limit($note, 250) : null,
                ...$post->only(BlogPostRevision::FIELDS),
            ]);
        }

        $this->log($actor, $oldStatus === null ? 'created' : ($oldStatus !== $post->status ? "{$oldStatus->value} → {$post->status->value}" : 'updated'), $post, $note);

        return $post->refresh();
    }

    /**
     * Send the post's address (and any old address that led to it) to another page.
     */
    private function redirectAway(BlogPost $post, string $target): void
    {
        $target = Redirect::normalizePath($target);

        if ($target === $post->path()) {
            throw new InvalidArgumentException('A post cannot redirect to itself.');
        }

        Redirect::moved($post->path(), $target);
        if ($post->legacy_url && $post->legacy_url !== $post->path()) {
            Redirect::moved($post->legacy_url, $target);
        }
    }

    /**
     * Tag ids for tag names or slugs, creating new tags.
     *
     * @param  array<int, mixed>  $tags
     * @return list<int>
     */
    private function tagIds(array $tags): array
    {
        $ids = [];
        foreach ($tags as $tag) {
            $name = trim((string) $tag);
            $slug = Str::slug($name, '-', 'ru');
            if ($slug === '') {
                continue;
            }
            $ids[] = (BlogTag::where('slug', $slug)->orWhere('slug', $name)->first() ?? BlogTag::create(['slug' => $slug, 'name' => $name]))->id;
        }

        return array_values(array_unique($ids));
    }

    private function log(BlogActor $actor, string $action, BlogPost $post, ?string $note): void
    {
        Log::info("blog: {$actor->name} {$action} /blog/{$post->slug}".($note ? " ({$note})" : ''));
    }
}
