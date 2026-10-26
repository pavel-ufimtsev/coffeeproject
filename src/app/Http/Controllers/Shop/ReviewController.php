<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ReviewRequest;
use App\Mail\ReviewSubmitted;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Take a review from the product page. It waits for an admin before it
 * shows; the shop gets an email so it does not wait long.
 */
class ReviewController extends Controller
{
    public function store(ReviewRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        abort_unless($product->status === ProductStatus::Active, 404);

        $email = mb_strtolower(trim($request->input('email')));

        $review = $product->reviews()->create([
            'user_id' => $request->user()?->id,
            'name' => trim($request->input('name')),
            'email' => $email,
            'rating' => (int) $request->input('rating'),
            'body' => trim($request->input('body')),
            'brew_method' => $request->input('brew_method') ?: null,
            'status' => ReviewStatus::Pending,
            'is_verified_buyer' => OrderItem::where('product_id', $product->id)
                ->whereHas('order', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('confirmed_at'))
                ->exists(),
        ]);

        Mail::to(config('shop.contact_email'))->queue(new ReviewSubmitted($review));

        $thanks = __('Thank you, :name! Your review will appear after a quick check.', ['name' => $review->name]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $thanks]);
        }

        return redirect(url($product->path()).'#reviews')->with('review_sent', $thanks);
    }
}
