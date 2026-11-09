<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A snapshot of a post's text taken on every save, so any change by a person
 * or an agent can be compared and undone.
 *
 * @property int $id
 * @property int $blog_post_id
 * @property int|null $user_id
 * @property string $author
 * @property string|null $note
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property Carbon|null $created_at
 */
#[Fillable(['blog_post_id', 'user_id', 'author', 'note', 'title', 'excerpt', 'body', 'seo_title', 'seo_description'])]
class BlogPostRevision extends Model
{
    public const UPDATED_AT = null;

    /** The post fields a revision keeps. */
    public const FIELDS = ['title', 'excerpt', 'body', 'seo_title', 'seo_description'];

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
