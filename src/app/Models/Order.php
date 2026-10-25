<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasDeliveryAddress;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A placed order. Contacts, address, lines and money are copies taken at
 * checkout; nothing here follows later changes to the catalog or the
 * customer's saved addresses.
 *
 * @property int $id
 * @property string|null $number
 * @property string $token
 * @property string $checkout_token
 * @property int|null $user_id
 * @property OrderStatus $status
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $email
 * @property DeliveryMethod $delivery_method
 * @property string $delivery_price
 * @property string $city
 * @property string $street
 * @property string $house
 * @property string|null $apartment
 * @property string|null $entrance
 * @property string|null $floor
 * @property string|null $intercom
 * @property string|null $courier_note
 * @property PaymentMethod $payment_method
 * @property bool $is_paid
 * @property string|null $payment_id
 * @property string|null $payment_status
 * @property int $payment_attempts
 * @property Carbon|null $paid_at
 * @property Carbon|null $confirmed_at
 * @property int|null $promo_code_id
 * @property string|null $promo_code
 * @property string $subtotal
 * @property string $discount
 * @property string $total
 * @property string|null $comment
 * @property Carbon $consent_at
 * @property string|null $ip
 * @property string|null $retailcrm_id
 * @property Carbon|null $retailcrm_pushed_at
 * @property string|null $retailcrm_error
 * @property Carbon $created_at
 */
class Order extends Model
{
    use HasDeliveryAddress;

    /** Every column is set by PlaceOrder or the admin, never from raw input. */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'payment_method' => PaymentMethod::class,
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'consent_at' => 'datetime',
            'retailcrm_pushed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function customerName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function formattedPhone(): string
    {
        return Phone::format($this->phone);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * Whether the customer still has to pay on the bank's page.
     */
    public function awaitsPayment(): bool
    {
        return $this->payment_method->isOnline() && ! $this->is_paid && $this->status !== OrderStatus::Cancelled;
    }

    /**
     * The online payment status in words, e.g. "Оплачен" or "Отклонён банком".
     */
    public function paymentStatusLabel(): ?string
    {
        if (! $this->payment_method->isOnline()) {
            return null;
        }

        return match ($this->payment_status) {
            null, 'NEW', 'FORM_SHOWED', 'AUTHORIZING', '3DS_CHECKING', '3DS_CHECKED' => __('Awaiting payment'),
            'AUTHORIZED' => __('Authorized, awaiting confirmation'),
            'CONFIRMED' => __('Paid'),
            'REJECTED', 'AUTH_FAIL' => __('Declined by the bank'),
            'CANCELED', 'DEADLINE_EXPIRED' => __('Payment cancelled'),
            'REFUNDED', 'REVERSED' => __('Refunded'),
            'PARTIAL_REFUNDED', 'PARTIAL_REVERSED' => __('Partly refunded'),
            default => $this->payment_status,
        };
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
