<x-mail.layout :heading="__('New review waiting for approval')" :preheader="\Illuminate\Support\Str::limit($review->body, 120)">
    <p style="margin: 0 0 8px; font-size: 15px; color: #8a8a8a;">{{ $review->product->name }}</p>
    <p style="margin: 0 0 16px; font-size: 20px; color: #c98a1b; letter-spacing: 2px;">{{ str_repeat('★', $review->rating) }}<span style="color: #dddddd;">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
    <div style="white-space: pre-line;">{{ $review->body }}</div>
    <p style="margin: 20px 0 0; font-size: 14px; color: #8a8a8a;">
        {{ $review->name }}, {{ $review->email }}@if ($review->brew_method) · {{ $review->brew_method }}@endif
        @if ($review->is_verified_buyer) · {{ __('Bought this coffee') }}@endif
    </p>
    <x-mail.button :url="$adminUrl">{{ __('Review in the admin') }}</x-mail.button>
</x-mail.layout>
