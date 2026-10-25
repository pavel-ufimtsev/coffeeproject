<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\Product;
use App\Support\Navigation;
use Illuminate\Support\Collection;
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
            'brewMethods' => $coffee ? $this->brewMethods($coffee) : collect(),
        ]);
    }

    /**
     * Brew-method landing pages with how many coffees suit each and the
     * lowest price among them; methods with nothing on sale are left out.
     *
     * @return Collection<int, array{method: FilterValue, count: int, from: float|null}>
     */
    private function brewMethods(Category $coffee): Collection
    {
        return FilterValue::brewLandings($coffee)
            ->with(['products' => fn ($query) => $query->active()->with('activeVariants')])
            ->get()
            ->map(fn (FilterValue $method) => [
                'method' => $method,
                'count' => $method->products->count(),
                'from' => $method->products->map->minPrice()->filter()->min(),
            ])
            ->filter(fn (array $item) => $item['count'] > 0)
            ->values();
    }
}
