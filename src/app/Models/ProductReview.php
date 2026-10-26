<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A shopper's review of a product. Anyone can leave one; it shows on the
 * site once an admin approves it.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property int $rating
 * @property string $body
 * @property string|null $brew_method
 * @property ReviewStatus $status
 * @property bool $is_verified_buyer
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 */
#[Fillable(['product_id', 'user_id', 'name', 'email', 'rating', 'body', 'brew_method', 'status', 'is_verified_buyer', 'approved_at'])]
class ProductReview extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'is_verified_buyer' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved);
    }
}
