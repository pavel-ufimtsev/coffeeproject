@php use App\Support\Money; @endphp
<x-layouts.account :title="__('My account')">
    <p>{{ __('Hello, :name.', ['name' => $user->name]) }}</p>

    <section class="card">
        <h2>{{ __('Orders') }}</h2>
        @if ($orders->isEmpty())
            <p class="muted">{{ __('You have not placed any orders yet.') }}</p>
            <p><a class="button button--secondary" href="{{ route('home') }}">{{ __('Browse coffee') }}</a></p>
        @else
            <ul class="order-list">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('account.orders.show', $order) }}" class="order-row">
                            <span class="order-row__main">
                                <strong>{{ $order->number }}</strong>
                                <span class="muted">{{ $order->created_at->translatedFormat('j F Y') }} · {{ trans_choice(':count item|:count items', (int) $order->item_count, ['count' => (int) $order->item_count]) }}</span>
                            </span>
                            <span class="order-status order-status--{{ $order->status->value }}">{{ $order->status->label() }}</span>
                            <span class="order-row__total">{{ Money::rub($order->total) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($orders->hasPages())
                <nav class="form-actions" aria-label="{{ __('Pages') }}">
                    @if ($orders->previousPageUrl())<a href="{{ $orders->previousPageUrl() }}" class="button button--secondary button--small">← {{ __('Newer') }}</a>@endif
                    @if ($orders->nextPageUrl())<a href="{{ $orders->nextPageUrl() }}" class="button button--secondary button--small">{{ __('Older') }} →</a>@endif
                </nav>
            @endif
        @endif
    </section>
</x-layouts.account>
