<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An internal label on blog posts. Tags have no public pages: they pick
 * related posts and help agents group articles. Old /blog/t/{slug} links
 * redirect to the category most of the tag's posts belong to.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 */
#[Fillable(['slug', 'name'])]
class BlogTag extends Model
{
    /**
     * @return BelongsToMany<BlogPost, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }
}
