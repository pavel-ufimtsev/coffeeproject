<?php

namespace App\Models;

use App\Models\Concerns\HasDeliveryAddress;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's saved delivery address ("Дом", "Работа"). The first one is
 * the default; checkout preselects the default and saves edits back to the
 * address picked.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property bool $is_default
 * @property string $city
 * @property string $street
 * @property string $house
 * @property string|null $apartment
 * @property string|null $entrance
 * @property string|null $floor
 * @property string|null $intercom
 * @property string|null $courier_note
 */
#[Fillable(['label', 'city', 'street', 'house', 'apartment', 'entrance', 'floor', 'intercom', 'courier_note'])]
class Address extends Model
{
    use HasDeliveryAddress;

    public const MAX_PER_USER = 10;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
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
     * Make this the user's default address.
     */
    public function makeDefault(): void
    {
        $this->user->addresses()->whereKeyNot($this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    protected static function booted(): void
    {
        // The first address is the default; deleting the default promotes the oldest one left.
        static::creating(function (Address $address) {
            if (! static::where('user_id', $address->user_id)->exists()) {
                $address->is_default = true;
            }
        });

        static::deleted(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)->oldest('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }
}
