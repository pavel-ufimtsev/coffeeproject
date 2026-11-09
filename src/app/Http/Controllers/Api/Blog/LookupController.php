<?php

namespace App\Http\Controllers\Api\Blog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Redirect;
use App\Support\BlogApiSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Products to link from posts, redirects for moved or retired posts, and the
 * API description.
 */
class LookupController extends BlogApiController
{
    /**
     * Products on sale (and archived ones on request), to embed with ::product[slug].
     */
    public function products(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200'], 'archived' => ['nullable', 'boolean']]);

        $products = Product::query()
            ->when($request->boolean('archived'), fn (Builder $q) => $q->visible(), fn (Builder $q) => $q->active())
            ->when($request->string('search')->toString(), fn (Builder $q, string $s) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")->orWhere('tagline', 'like', "%{$s}%")))
            ->with(['category.parent', 'activeVariants'])
            ->orderBy('sort')
            ->limit(100)
            ->get();

        return response()->json(['data' => $products->map(fn (Product $p) => [
            'slug' => $p->slug,
            'name' => $p->name,
            'status' => $p->status->value,
            'on_sale' => $p->status === ProductStatus::Active,
            'url' => url($p->path()),
            'category' => $p->category->name,
            'tagline' => $p->tagline,
            'price_from' => $p->minPrice(),
            'markdown' => "::product[{$p->slug}]",
        ])]);
    }

    public function redirects(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        $redirects = Redirect::query()
            ->when($request->string('search')->toString(), fn (Builder $q, string $s) => $q->where('from_path', 'like', "%{$s}%")->orWhere('to_path', 'like', "%{$s}%"))
            ->orderBy('from_path')
            ->limit(500)
            ->get(['id', 'from_path', 'to_path', 'status_code', 'hits']);

        return response()->json(['data' => $redirects]);
    }

    /**
     * Send an old address to a new one, e.g. after merging two posts.
     */
    public function storeRedirect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:255', self::PATH_RULE],
            'to' => ['required', 'string', 'max:255', self::PATH_RULE, 'different:from'],
        ]);

        $from = Redirect::normalizePath($data['from']);
        $to = Redirect::normalizePath($data['to']);
        Redirect::moved($from, $to);
        Log::info("blog: {$this->actor($request)->name} redirected {$from} to {$to}");

        return response()->json(['data' => Redirect::where('from_path', $from)->first(['id', 'from_path', 'to_path', 'status_code'])], 201);
    }

    public function destroyRedirect(Request $request, Redirect $redirect): JsonResponse
    {
        $redirect->delete();
        Log::info("blog: {$this->actor($request)->name} removed redirect {$redirect->from_path}");

        return response()->json(['deleted' => true]);
    }

    public function schema(): JsonResponse
    {
        return response()->json(BlogApiSchema::openApi());
    }
}
