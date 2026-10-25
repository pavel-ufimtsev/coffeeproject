{{-- Another try on the bank's page, or paying the courier instead. --}}
<section class="order-card order-card--payment">
    <h2>{{ $title }}</h2>
    <p>{{ __('The order :number is saved. Pay :total by card, SBP or T-Pay to confirm it.', ['number' => $order->number, 'total' => \App\Support\Money::rub($order->total)]) }}</p>
    @if (session('payment_error'))
        <p class="notice notice--warning" role="alert">{{ session('payment_error') }}</p>
    @endif
    <div class="payment-actions">
        <form method="POST" action="{{ route('payment.retry', $order->token) }}">
            @csrf
            <button type="submit" class="button button--large checkout-submit__button">{{ __('Pay :total', ['total' => \App\Support\Money::rub($order->total)]) }}</button>
        </form>
        @if ($canPayOnDelivery ?? \App\Enums\PaymentMethod::Cash->isEnabled())
            <form method="POST" action="{{ route('payment.cash', $order->token) }}">
                @csrf
                <button type="submit" class="button button--large button--secondary">{{ __('Pay on delivery instead') }}</button>
            </form>
        @endif
    </div>
</section>
