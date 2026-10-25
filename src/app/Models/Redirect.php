<?php

namespace App\Models;

use App\Http\Middleware\RedirectLegacyUrls;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * A permanent redirect from an old URL, mostly from the previous site.
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property int $hits
 * @property Carbon|null $last_hit_at
 */
#[Fillable(['from_path', 'to_path', 'status_code'])]
class Redirect extends Model
{
    /**
     * Forget the cached redirect map whenever a redirect changes.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(RedirectLegacyUrls::CACHE_KEY));
        static::deleted(fn () => Cache::forget(RedirectLegacyUrls::CACHE_KEY));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * Record that a page moved: the old address redirects to the new one,
     * redirects that led to the old address now lead straight to the new
     * one, and nothing redirects away from the new address any more.
     */
    public static function moved(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        static::where('to_path', $from)->get()->each->update(['to_path' => $to]);
        static::where('from_path', $to)->get()->each->delete();
        static::updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301]);
    }

    /**
     * Make an address usable for a new page by removing any redirect away from it.
     */
    public static function release(string $path): void
    {
        static::where('from_path', $path)->get()->each->delete();
    }

    /**
     * Normalise a request path the way redirects are stored: leading slash,
     * no trailing slash, decoded.
     */
    public static function normalizePath(string $path): string
    {
        return '/'.trim(rawurldecode($path), '/');
    }
}
