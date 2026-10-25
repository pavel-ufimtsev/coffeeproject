<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ImageProcessor;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductImageController extends Controller
{
    /**
     * Upload photos. They are converted to WebP and added after the existing ones.
     */
    public function store(Request $request, Product $product, ImageProcessor $processor): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:20'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
        ]);

        $sort = (int) $product->images()->max('sort') + 1;

        foreach ($request->file('images') as $file) {
            $stored = $processor->store($file->getRealPath(), $product->slug.'-'.Str::lower(Str::random(6)));
            $product->images()->create([...$stored, 'alt' => $product->name, 'sort' => $sort++, 'source' => 'upload']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photos uploaded.')]);

        return back();
    }

    /**
     * Save the order of the photos. The first one is the main photo.
     */
    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::apply($product->images(), $ids);

        return back();
    }

    /**
     * Update a photo's alt text.
     */
    public function update(Request $request, ProductImage $image): RedirectResponse
    {
        $image->update($request->validate(['alt' => ['nullable', 'string', 'max:255']]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo description saved.')]);

        return back();
    }

    /**
     * Delete a photo and its files.
     */
    public function destroy(ProductImage $image): RedirectResponse
    {
        Storage::disk('public')->delete([$image->path, $image->thumb_path]);
        $image->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo deleted.')]);

        return back();
    }
}
