<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A product photo, stored on the public disk in a large and a thumbnail size.
 *
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property string $thumb_path
 * @property string|null $alt
 * @property int $sort
 * @property string $source
 */
#[Fillable(['product_id', 'path', 'thumb_path', 'alt', 'sort', 'source'])]
class ProductImage extends Model
{
    /** Width in pixels of the large image. */
    public const LARGE_WIDTH = 1200;

    /** Width in pixels of the thumbnail. */
    public const THUMB_WIDTH = 600;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
