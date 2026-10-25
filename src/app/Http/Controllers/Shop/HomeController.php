<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\FilterValue;
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
        $roots = Navigation::roots();
        $coffee = $roots->firstWhere('slug', 'coffee');

        return view('shop.home', [
            'featured' => Product::active()
                ->where('is_featured', true)
                ->with(['activeVariants', 'mainImage', 'roaster', 'category.parent'])
                ->orderBy('sort')
                ->limit(8)
                ->get(),
            'roots' => $roots,
            'coffee' => $coffee,
            'brewMethods' => $coffee ? FilterValue::brewLandings($coffee)->get() : collect(),
        ]);
    }
}
