<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * List reviews, the ones waiting for approval first.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $status = $filters['status'] ?? ReviewStatus::Pending->value;

        $reviews = ProductReview::query()
            ->with('product.category.parent')
            ->where('status', $status)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")
                ->orWhereRelation('product', 'name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ProductReview $review) => [
                ...$review->only(['id', 'name', 'email', 'rating', 'body', 'brew_method', 'is_verified_buyer']),
                'status' => $review->status->value,
                'created_at' => $review->created_at->format('d.m.Y H:i'),
                'product' => ['name' => $review->product->name, 'url' => url($review->product->path())],
            ]);

        return Inertia::render('admin/reviews/Index', [
            'reviews' => $reviews,
            'filters' => ['status' => $status, 'search' => $filters['search'] ?? ''],
            'counts' => collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $s) => [
                $s->value => ProductReview::where('status', $s)->count(),
            ]),
        ]);
    }

    /**
     * Approve or reject a review.
     */
    public function update(Request $request, ProductReview $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ReviewStatus::class)]]);
        $status = ReviewStatus::from($data['status']);

        $review->update([
            'status' => $status,
            'approved_at' => $status === ReviewStatus::Approved ? ($review->approved_at ?? now()) : null,
        ]);

        return back()->with('status', $status === ReviewStatus::Approved ? __('Review published.') : __('Review hidden.'));
    }

    public function destroy(ProductReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('status', __('Review deleted.'));
    }
}
