<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Shop settings edited in the admin, e.g. courier prices. Read through
 * Setting::get(), which caches the whole table.
 *
 * @property string $key
 * @property mixed $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Get a setting, or the default when it has never been saved.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Save several settings at once.
     *
     * @param  array<string, mixed>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
