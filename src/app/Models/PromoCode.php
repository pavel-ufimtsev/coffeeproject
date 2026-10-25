<?php

namespace App\Models;

use App\Enums\PromoType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A promo code: a percentage or a fixed amount off the goods (never off
 * delivery). Codes are stored in upper case and matched case-insensitively.
 *
 * @property int $id
 * @property string $code
 * @property PromoType $type
 * @property string $value
 * @property string|null $min_subtotal
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $max_uses
 * @property int $used_count
 * @property bool $is_active
 * @property string|null $note
 */
#[Fillable(['code', 'type', 'value', 'min_subtotal', 'starts_at', 'ends_at', 'max_uses', 'is_active', 'note'])]
class PromoCode extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromoType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', static::normalize($code))->first();
    }

    /**
     * Why the code cannot be used on goods worth $subtotal, or null when it can.
     */
    public function problemFor(float $subtotal): ?string
    {
        return match (true) {
            ! $this->is_active,
            $this->starts_at?->isFuture() === true => __('This promo code is not valid.'),
            $this->ends_at?->isPast() === true => __('This promo code has expired.'),
            $this->max_uses !== null && $this->used_count >= $this->max_uses => __('This promo code has been used up.'),
            $this->min_subtotal !== null && $subtotal < (float) $this->min_subtotal => __('This promo code works on orders from :amount.', ['amount' => Money::rub($this->min_subtotal)]),
            default => null,
        };
    }

    /**
     * The discount on goods worth $subtotal, in whole rubles and never more than the goods.
     */
    public function discountFor(float $subtotal): float
    {
        $discount = match ($this->type) {
            PromoType::Percent => floor($subtotal * (float) $this->value / 100),
            PromoType::Fixed => (float) $this->value,
        };

        return min($subtotal, max(0, $discount));
    }

    /**
     * "−10 %" or "−300 ₽", for the admin list.
     */
    public function valueLabel(): string
    {
        return $this->type === PromoType::Percent
            ? '−'.rtrim(rtrim($this->value, '0'), '.')."\u{00A0}%"
            : '−'.Money::rub($this->value);
    }
}
