<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\RetailCrm\CatalogSync;
use App\Services\RetailCrm\ProductLinker;
use App\Services\RetailCrm\RetailCrmException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductRetailCrmController extends Controller
{
    /**
     * Link the product to a retailCRM product; the chosen offers become its variants.
     */
    public function store(Request $request, Product $product, ProductLinker $linker): RedirectResponse
    {
        $data = $request->validate([
            'retailcrm_product_id' => ['required', 'integer', 'min:1', Rule::unique('products')->ignore($product)],
            'offer_ids' => ['required', 'array'],
            'offer_ids.*' => ['integer'],
        ], [
            'retailcrm_product_id.unique' => __('Another product is already linked to this retailCRM product.'),
        ]);

        try {
            $linker->link($product, $linker->fetch((int) $data['retailcrm_product_id']), array_values(array_map('intval', $data['offer_ids'])));
        } catch (RetailCrmException $e) {
            throw ValidationException::withMessages(['retailcrm_product_id' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Linked to retailCRM. Prices are up to date.')]);

        return back();
    }

    /**
     * Fetch the product's prices from retailCRM now.
     */
    public function update(Product $product, CatalogSync $sync): RedirectResponse
    {
        try {
            $problems = $sync->syncProduct($product);
        } catch (RetailCrmException $e) {
            throw ValidationException::withMessages(['retailcrm_product_id' => $e->getMessage()]);
        }

        Inertia::flash('toast', $problems === []
            ? ['type' => 'success', 'message' => __('Prices updated from retailCRM.')]
            : ['type' => 'warning', 'message' => collect($problems)->pluck('problem')->unique()->implode(' ')]);

        return back();
    }
}
