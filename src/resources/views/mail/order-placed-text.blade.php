@php use App\Support\Money; @endphp
{{ $texts['heading'] }}

{{ $texts['intro_text'] }}

@foreach ($order->items as $item)
{{ $item->name }}@if ($item->meta()) ({{ $item->meta() }})@endif, {{ $item->quantity }} × {{ Money::rub($item->price) }} = {{ Money::rub($item->total) }}
@endforeach

{{ __('Goods') }}: {{ Money::rub($order->subtotal) }}
@if ((float) $order->discount > 0)
{{ __('Promo code :code', ['code' => $order->promo_code]) }}: −{{ Money::rub($order->discount) }}
@endif
{{ __('Delivery') }}: {{ (float) $order->delivery_price > 0 ? Money::rub($order->delivery_price) : __('Free') }}
{{ __('Total') }}: {{ Money::rub($order->total) }}

{{ __('Delivery') }}: {{ $order->delivery_method->label() }}, {{ $order->destinationLine() }}
{{ __('Payment') }}: {{ $order->payment_method->label() }}
{{ __('Recipient') }}: {{ $order->customerName() }}, {{ $order->formattedPhone() }}

{{ __('View order') }}: {{ $url }}

{{ $texts['outro_text'] }}

--
{{ config('shop.name') }}, {{ config('shop.phone') }}, {{ config('shop.email') }}
