<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class YandexFeedController extends Controller
{
    /**
     * Product feed in Yandex YML format for Yandex Products: one offer per
     * variant on sale, grouped by product.
     */
    public function __invoke(): Response
    {
        $products = Product::active()
            ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])
            ->orderBy('sort')
            ->get()
            ->filter(fn (Product $p) => $p->isPurchasable());

        return SitemapController::xml(view('feeds.yandex', [
            'categories' => Category::where('is_active', true)->orderBy('parent_id')->orderBy('sort')->get(),
            'products' => $products,
            'date' => now()->format('Y-m-d\TH:iP'),
        ])->render());
    }
}
