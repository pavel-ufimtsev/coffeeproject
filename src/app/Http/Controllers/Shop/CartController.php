<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddToCartRequest;
use App\Services\Cart\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cart drawer's endpoints. Script requests get JSON with the redrawn
 * drawer; without JavaScript the forms post here and land on /cart.
 */
class CartController extends Controller
{
    public function show(Request $request, Cart $cart): View|JsonResponse
    {
        if ($request->wantsJson()) {
            return $this->drawer($cart);
        }

        return view('shop.cart.show', ['cart' => $cart]);
    }

    public function store(AddToCartRequest $request, Cart $cart): JsonResponse|RedirectResponse
    {
        $key = $cart->add($request->variant(), $request->grind(), $request->integer('quantity', 1));

        return $this->respond($request, $cart, __(':name is in your cart.', ['name' => $request->variant()->product->name]), $key);
    }

    public function update(Request $request, Cart $cart, string $key): JsonResponse|RedirectResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY]]);

        $cart->update($key, $request->integer('quantity'));

        return $this->respond($request, $cart);
    }

    public function destroy(Request $request, Cart $cart, string $key): JsonResponse|RedirectResponse
    {
        $cart->remove($key);

        return $this->respond($request, $cart);
    }

    /**
     * @param  string|null  $added  The line just added, highlighted in the drawer.
     */
    private function respond(Request $request, Cart $cart, ?string $status = null, ?string $added = null): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return $this->drawer($cart, $added);
        }

        return to_route('cart.show')->with('status', $status);
    }

    private function drawer(Cart $cart, ?string $added = null): JsonResponse
    {
        return response()->json([
            'count' => $cart->count(),
            'added' => $added,
            'html' => view('shop.cart.contents', ['cart' => $cart])->render(),
        ]);
    }
}
