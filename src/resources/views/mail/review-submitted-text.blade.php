{{ __('New review waiting for approval') }}

{{ $review->product->name }}: {{ $review->rating }}/5

{{ $review->body }}

{{ $review->name }}, {{ $review->email }}@if ($review->brew_method) · {{ $review->brew_method }}@endif

{{ __('Review in the admin') }}: {{ $adminUrl }}
