@php use App\Support\Money; @endphp
<x-layouts.checkout :title="__('Order :number', ['number' => $order->number])">
    <div class="checkout checkout--complete">
        <div class="checkout__main">
            <div class="order-done">
                <span class="order-done__icon" aria-hidden="true">
                    <svg viewBox="0 0 52 52" width="52" height="52" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="26" cy="26" r="24" class="order-done__circle"/><path d="m15 27 7.5 7.5L37 19" class="order-done__check"/></svg>
                </span>
                <div>
                    <p class="order-done__number">{{ __('Order :number', ['number' => $order->number]) }}</p>
                    <h1 class="order-done__title">{{ __('Thank you, :name!', ['name' => $order->first_name]) }}</h1>
                </div>
            </div>

            @if ($order->awaitsPayment())
                @include('shop.payment.actions', ['title' => __('The order is waiting for payment')])
            @else
                <section class="order-card">
                    <h2>{{ $order->is_paid ? __('Paid, thank you') : __('Your order is placed') }}</h2>
                    <p>{{ __('We will call you at :phone to confirm the order and agree on a delivery time.', ['phone' => $order->formattedPhone()]) }}</p>
                    <p class="muted">{{ __('The confirmation is on its way to :email.', ['email' => $order->email]) }}</p>
                </section>
            @endif

            <section class="order-card">
                <h2>{{ __('Order details') }}</h2>
                <dl class="order-details">
                    <div>
                        <dt>{{ __('Contacts') }}</dt>
                        <dd>{{ $order->customerName() }}<br>{{ $order->formattedPhone() }}<br>{{ $order->email }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Delivery address') }}</dt>
                        <dd>
                            {{ $order->addressLine() }}
                            @if ($order->accessLine())<br>{{ $order->accessLine() }}@endif
                            @if ($order->courier_note)<br><span class="muted">{{ $order->courier_note }}</span>@endif
                        </dd>
                    </div>
                    <div>
                        <dt>{{ __('Delivery method') }}</dt>
                        <dd>{{ __('Courier in Ekaterinburg') }} · {{ (float) $order->delivery_price > 0 ? Money::rub($order->delivery_price) : __('Free') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Payment') }}</dt>
                        <dd>
                            {{ $order->payment_method->label() }}
                            @if ($order->paymentStatusLabel())<br><span @class(['muted' => ! $order->is_paid, 'order-paid' => $order->is_paid])>{{ $order->paymentStatusLabel() }}</span>@endif
                        </dd>
                    </div>
                    @if ($order->comment)
                        <div class="order-details__wide">
                            <dt>{{ __('Order comment') }}</dt>
                            <dd>{{ $order->comment }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            @unless ($hasAccount)
                <section class="order-card order-card--invite">
                    <div>
                        <h2>{{ __('Save your details for next time') }}</h2>
                        <p class="muted">{{ __('With an account your addresses are filled in for you and your orders are in one place.') }}</p>
                    </div>
                    <a href="{{ route('register', ['email' => $order->email]) }}" class="button">{{ __('Create account') }}</a>
                </section>
            @endunless

            <div class="order-actions">
                <a href="{{ url('coffee') }}" class="button button--large">{{ __('Continue shopping') }}</a>
                <p class="muted">{{ __('Questions? Call :phone.', ['phone' => config('shop.phone')]) }}</p>
            </div>
        </div>

        @include('shop.checkout.order-summary')
    </div>
</x-layouts.checkout>
