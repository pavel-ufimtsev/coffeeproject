<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Navigation
{
    public const CACHE_KEY = 'navigation.root-ids';

    /**
     * Root categories that have something on sale, for the site header and
     * footer. Which ones qualify is cached briefly (as IDs: the cache only
     * stores plain data) because every storefront page asks.
     *
     * @return Collection<int, Category>
     */
    public static function roots(): Collection
    {
        $ids = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), fn () => Category::roots()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Category $root) => Product::active()->whereIn('category_id', $root->selfAndDescendantIds())->exists())
            ->pluck('id')
            ->all());

        return Category::whereIn('id', $ids)->orderBy('sort')->get();
    }
}
