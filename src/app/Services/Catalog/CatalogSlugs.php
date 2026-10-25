<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Child categories, landing pages and products of a root category all live at
 * /{root}/{slug}, so a slug may be used by only one of them.
 */
class CatalogSlugs
{
    /**
     * Describe what already uses /{root}/{slug}, or null when it is free.
     */
    public static function conflict(Category $root, string $slug, ?Model $ignore = null): ?string
    {
        $candidates = [
            __('a category') => Category::where('parent_id', $root->id)->where('slug', $slug),
            __('a filter landing page') => FilterValue::where('slug', $slug)->where('is_landing', true)
                ->whereRelation('group', 'category_id', $root->id),
            __('a collection') => ProductCollection::where('category_id', $root->id)->where('slug', $slug)->where('is_landing', true),
            __('a product') => Product::where('slug', $slug)->whereIn('category_id', $root->selfAndDescendantIds()),
        ];

        foreach ($candidates as $label => $query) {
            if ($ignore !== null && $query->getModel() instanceof $ignore) {
                $query->whereKeyNot($ignore->getKey());
            }

            if ($query->exists()) {
                return __('The address /:root/:slug is already used by :what.', ['root' => $root->slug, 'slug' => $slug, 'what' => $label]);
            }
        }

        return null;
    }
}
