<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Navigation;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the storefront home page.
     */
    public function __invoke(): View
    {
        return view('shop.home', [
            'featured' => Product::active()
                ->where('is_featured', true)
                ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])
                ->orderBy('sort')
                ->limit(8)
                ->get(),
            'roots' => Navigation::roots(),
        ]);
    }
}
