<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RetailCrmIgnoredProduct;
use App\Models\RetailCrmSyncRun;
use App\Services\RetailCrm\CatalogSync;
use App\Services\RetailCrm\ProductLinker;
use App\Services\RetailCrm\RetailCrm;
use App\Services\RetailCrm\RetailCrmException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RetailCrmController extends Controller
{
    /** Cache key of the active retailCRM products, fetched for the unlinked list. */
    public const ACTIVE_PRODUCTS_CACHE_KEY = 'retailcrm.active-products';

    /**
     * Show the sync history and the problems found by the latest run.
     */
    public function index(RetailCrm $crm): Response
    {
        return Inertia::render('admin/retailcrm/Index', [
            'configured' => $crm->isConfigured(),
            'url' => $crm->url(),
            'runs' => RetailCrmSyncRun::latest('id')->limit(20)->get()->map(fn (RetailCrmSyncRun $run) => [
                ...$run->only(['id', 'products_checked', 'variants_changed', 'error']),
                'started_at' => $run->started_at->toDateTimeString(),
                'duration' => $run->finished_at ? (int) $run->started_at->diffInSeconds($run->finished_at) : null,
                'changes' => $run->changes ?? [],
                'issues' => $run->issues ?? [],
            ]),
            'productIds' => Product::pluck('id', 'slug'),
        ]);
    }

    /**
     * Run the catalog sync now.
     */
    public function sync(CatalogSync $sync): RedirectResponse
    {
        $run = $sync->run();

        Inertia::flash('toast', match (true) {
            $run->error !== null => ['type' => 'error', 'message' => $run->error],
            (bool) $run->issues => ['type' => 'warning', 'message' => __('Sync finished: :changed prices changed, :issues problems.', ['changed' => $run->variants_changed, 'issues' => count($run->issues)])],
            default => ['type' => 'success', 'message' => __('Sync finished: :changed prices changed.', ['changed' => $run->variants_changed])],
        });

        return back();
    }

    /**
     * Search retailCRM products for the link picker.
     */
    public function search(Request $request, RetailCrm $crm): JsonResponse
    {
        $term = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);

        try {
            $products = $crm->searchProducts($term);
        } catch (RetailCrmException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $linkedProducts = Product::whereIn('retailcrm_product_id', array_column($products, 'id'))->pluck('name', 'retailcrm_product_id');
        $offerIds = collect($products)->flatMap(fn (array $p) => array_column($p['offers'] ?? [], 'id'));
        $linkedOffers = ProductVariant::whereIn('retailcrm_offer_id', $offerIds)->with('product:id,name')->get()->keyBy('retailcrm_offer_id');

        return response()->json(['products' => array_map(fn (array $product) => [
            'id' => (int) $product['id'],
            'name' => $product['name'] ?? '',
            'article' => $product['article'] ?? null,
            'active' => (bool) ($product['active'] ?? false),
            'linked_to' => $linkedProducts[$product['id']] ?? null,
            'offers' => array_map(fn (array $offer) => [
                'id' => (int) $offer['id'],
                'name' => $offer['name'] ?? '',
                'label' => ProductLinker::label((string) ($product['name'] ?? ''), $offer),
                'price' => isset($offer['price']) ? (float) $offer['price'] : null,
                'active' => (bool) ($offer['active'] ?? true),
                'linked_to' => $linkedOffers->get($offer['id'])?->product->name,
            ], $product['offers'] ?? []),
        ], $products)]);
    }

    /**
     * List active retailCRM products the site neither sells nor ignores, so
     * new items are noticed.
     */
    public function unlinked(Request $request, RetailCrm $crm): Response
    {
        if ($request->boolean('refresh')) {
            Cache::forget(self::ACTIVE_PRODUCTS_CACHE_KEY);
        }

        $error = null;
        $active = [];

        if ($crm->isConfigured()) {
            try {
                $active = Cache::remember(self::ACTIVE_PRODUCTS_CACHE_KEY, now()->addMinutes(10), fn () => array_map(fn (array $product) => [
                    'id' => (int) $product['id'],
                    'name' => $product['name'] ?? '',
                    'article' => $product['article'] ?? null,
                    'created_at' => $product['createdAt'] ?? null,
                    'offers' => array_map(fn (array $offer) => [
                        'name' => $offer['name'] ?? '',
                        'price' => isset($offer['price']) ? (float) $offer['price'] : null,
                    ], $product['offers'] ?? []),
                ], array_values($crm->activeProducts())));
            } catch (RetailCrmException $e) {
                $error = $e->getMessage();
            }
        }

        $linked = Product::whereNotNull('retailcrm_product_id')->pluck('retailcrm_product_id')->map(fn ($id) => (int) $id)->flip();
        $ignored = RetailCrmIgnoredProduct::orderBy('name')->get();
        $ignoredIds = $ignored->pluck('retailcrm_product_id')->map(fn ($id) => (int) $id)->flip();

        return Inertia::render('admin/retailcrm/Unlinked', [
            'configured' => $crm->isConfigured(),
            'error' => $error,
            'products' => collect($active)
                ->reject(fn (array $product) => $linked->has($product['id']) || $ignoredIds->has($product['id']))
                ->sortByDesc('created_at')
                ->values(),
            'ignored' => $ignored->map(fn (RetailCrmIgnoredProduct $product) => $product->only(['id', 'retailcrm_product_id', 'name'])),
        ]);
    }

    /**
     * Stop listing retailCRM products as unlinked.
     */
    public function ignore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'products' => ['required', 'array'],
            'products.*.id' => ['required', 'integer', 'min:1'],
            'products.*.name' => ['required', 'string', 'max:255'],
        ]);

        foreach ($data['products'] as $product) {
            RetailCrmIgnoredProduct::updateOrCreate(['retailcrm_product_id' => $product['id']], ['name' => $product['name']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Hidden from the list: :count.', ['count' => count($data['products'])])]);

        return back();
    }

    /**
     * List an ignored retailCRM product as unlinked again.
     */
    public function unignore(RetailCrmIgnoredProduct $ignored): RedirectResponse
    {
        $ignored->delete();

        return back();
    }
}
