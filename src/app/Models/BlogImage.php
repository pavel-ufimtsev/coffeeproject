<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A blog image on the public disk, in a large and a thumbnail size. One image
 * can be used by many posts.
 *
 * @property int $id
 * @property string $path
 * @property string $thumb_path
 * @property int|null $width
 * @property int|null $height
 * @property string|null $original_name
 * @property string|null $alt
 * @property string|null $caption
 * @property string|null $credit
 * @property string|null $uploaded_by
 */
#[Fillable(['path', 'thumb_path', 'width', 'height', 'original_name', 'alt', 'caption', 'credit', 'uploaded_by'])]
class BlogImage extends Model
{
    /** Width in pixels of the large image. */
    public const LARGE_WIDTH = 1600;

    /** Width in pixels of the thumbnail used on post cards. */
    public const THUMB_WIDTH = 640;

    /**
     * @return BelongsToMany<BlogPost, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_image_post');
    }

    /**
     * Get the public URL of the large image.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Get the public URL of the thumbnail.
     */
    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path);
    }
}
