@php use App\Support\Money; @endphp
<x-layouts.account :title="__('Order :number', ['number' => $order->number])">
    <p><a href="{{ route('account.index') }}">← {{ __('All orders') }}</a></p>

    <section class="card">
        <div class="order-head">
            <span class="muted">{{ $order->created_at->translatedFormat('j F Y, H:i') }}</span>
            <span class="order-status order-status--{{ $order->status->value }}">{{ $order->status->label() }}</span>
        </div>

        <ul class="summary-lines">
            @foreach ($order->items as $item)
                <li class="summary-line">
                    <span class="summary-line__media">
                        @if ($item->product?->mainImage)
                            <img src="{{ $item->product->mainImage->thumbUrl() }}" alt="" width="64" height="64">
                        @endif
                        <span class="summary-line__qty" aria-label="{{ __('Quantity') }}">{{ $item->quantity }}</span>
                    </span>
                    <span class="summary-line__info">
                        @if ($item->product)
                            <a href="{{ url($item->product->path()) }}" class="summary-line__name">{{ $item->name }}</a>
                        @else
                            <span class="summary-line__name">{{ $item->name }}</span>
                        @endif
                        <span class="summary-line__meta">{{ $item->meta() }}</span>
                    </span>
                    <span class="summary-line__total">{{ Money::rub($item->total) }}</span>
                </li>
            @endforeach
        </ul>

        <dl class="summary-totals">
            <div>
                <dt>{{ __('Goods · :count pcs.', ['count' => $order->itemCount()]) }}</dt>
                <dd>{{ Money::rub($order->subtotal) }}</dd>
            </div>
            @if ((float) $order->discount > 0)
                <div class="summary-totals__discount">
                    <dt>{{ __('Promo code :code', ['code' => $order->promo_code]) }}</dt>
                    <dd>−{{ Money::rub($order->discount) }}</dd>
                </div>
            @endif
            <div>
                <dt>{{ __('Delivery') }}</dt>
                <dd>{{ (float) $order->delivery_price > 0 ? Money::rub($order->delivery_price) : __('Free') }}</dd>
            </div>
            <div class="summary-totals__total">
                <dt>{{ __('Total') }}</dt>
                <dd>{{ Money::rub($order->total) }}</dd>
            </div>
        </dl>
    </section>

    <section class="card">
        <dl class="order-details">
            <div>
                <dt>{{ __('Recipient') }}</dt>
                <dd>{{ $order->customerName() }}<br>{{ $order->formattedPhone() }}</dd>
            </div>
            <div>
                <dt>{{ __('Delivery address') }}</dt>
                <dd>
                    {{ $order->addressLine() }}
                    @if ($order->accessLine())<br>{{ $order->accessLine() }}@endif
                </dd>
            </div>
            <div>
                <dt>{{ __('Payment') }}</dt>
                <dd>
                    {{ $order->payment_method->label() }}
                    @if ($order->paymentStatusLabel())<br><span @class(['muted' => ! $order->is_paid, 'order-paid' => $order->is_paid])>{{ $order->paymentStatusLabel() }}</span>@endif
                </dd>
            </div>
            @if ($order->awaitsPayment())
                <div>
                    <dt>&nbsp;</dt>
                    <dd><a href="{{ route('checkout.complete', $order->token) }}" class="button button--small">{{ __('Pay :total', ['total' => \App\Support\Money::rub($order->total)]) }}</a></dd>
                </div>
            @endif
        </dl>
    </section>
</x-layouts.account>
