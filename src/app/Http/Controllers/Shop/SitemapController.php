<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * List every indexable storefront page: the company, help and quiz pages,
     * categories and landing pages with enough products, and active products.
     */
    public function __invoke(): Response
    {
        $min = config('catalog.min_indexable_products');
        $urls = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('about'), 'lastmod' => null],
            ['loc' => route('contacts'), 'lastmod' => null],
            ['loc' => route('wholesale'), 'lastmod' => null],
            ['loc' => route('quiz'), 'lastmod' => null],
            ['loc' => route('help'), 'lastmod' => null],
            ['loc' => route('help.delivery'), 'lastmod' => null],
            ['loc' => route('help.returns'), 'lastmod' => null],
            ['loc' => route('help.faq'), 'lastmod' => null],
        ];

        foreach (array_keys(config('shop.delivery_cities')) as $city) {
            $urls[] = ['loc' => route('help.delivery.city', $city), 'lastmod' => null];
        }

        $products = Product::active()->with('category.parent', 'filterValues', 'collections')->get();

        foreach (Category::where('is_active', true)->orderBy('parent_id')->orderBy('sort')->get() as $category) {
            $ids = $category->selfAndDescendantIds();
            if ($products->whereIn('category_id', $ids)->count() >= $min) {
                $urls[] = ['loc' => url($category->path()), 'lastmod' => null];
            }
        }

        foreach (FilterValue::where('is_landing', true)->with('group.category')->orderBy('sort')->get() as $value) {
            if ($products->filter(fn (Product $p) => $p->filterValues->contains('id', $value->id))->count() >= $min) {
                $urls[] = ['loc' => url("/{$value->group->category->slug}/{$value->slug}"), 'lastmod' => null];
            }
        }

        foreach (ProductCollection::where('is_landing', true)->with('category')->orderBy('sort')->get() as $collection) {
            if ($products->filter(fn (Product $p) => $p->collections->contains('id', $collection->id))->count() >= $min) {
                $urls[] = ['loc' => url("/{$collection->category->slug}/{$collection->slug}"), 'lastmod' => null];
            }
        }

        foreach ($products->sortBy('sort') as $product) {
            $urls[] = ['loc' => url($product->path()), 'lastmod' => $product->updated_at?->toAtomString()];
        }

        return self::xml(view('feeds.sitemap', ['urls' => $urls])->render());
    }

    /**
     * Wrap rendered XML in a response. The declaration is added here because a
     * Blade template cannot contain "?>".
     */
    public static function xml(string $body): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
