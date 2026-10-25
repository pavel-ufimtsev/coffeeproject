@php use App\Support\Money; @endphp
<aside class="checkout__aside" aria-label="{{ __('Order summary') }}">
    <div class="checkout-summary">
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
                        <span class="summary-line__name">{{ $item->name }}</span>
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
    </div>
</aside>
