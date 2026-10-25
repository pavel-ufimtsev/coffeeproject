<x-layouts.checkout :title="__('Payment did not go through')">
    <div class="checkout checkout--complete">
        <div class="checkout__main">
            <div class="order-done order-done--failed">
                <span class="order-done__icon" aria-hidden="true">
                    <svg viewBox="0 0 52 52" width="52" height="52" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><circle cx="26" cy="26" r="24"/><path d="M26 15v14M26 36v.5"/></svg>
                </span>
                <div>
                    <p class="order-done__number">{{ __('Order :number', ['number' => $order->number]) }}</p>
                    <h1 class="order-done__title">{{ __('Payment did not go through') }}</h1>
                </div>
            </div>

            @include('shop.payment.actions', ['title' => __('Your order is saved')])

            <p class="muted">{{ __('If the bank declined the card, try another card or SBP. Questions? Call :phone.', ['phone' => config('shop.phone')]) }}</p>
        </div>

        @include('shop.checkout.order-summary')
    </div>
</x-layouts.checkout>
