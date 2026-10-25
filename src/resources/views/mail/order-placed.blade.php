@php use App\Support\Money; @endphp
<x-mail::message>
# {{ __('Thank you, :name!', ['name' => $order->first_name]) }}

{{ __('We have received order :number and will call you to confirm the delivery time.', ['number' => $order->number]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Qty') }} | {{ __('Sum') }} |
|:--|:-:|--:|
@foreach ($order->items as $item)
| {{ $item->name }}@if ($item->meta())<br><small>{{ $item->meta() }}</small>@endif | {{ $item->quantity }} | {{ Money::rub($item->total) }} |
@endforeach
| {{ __('Goods') }} | | {{ Money::rub($order->subtotal) }} |
@if ((float) $order->discount > 0)
| {{ __('Promo code :code', ['code' => $order->promo_code]) }} | | −{{ Money::rub($order->discount) }} |
@endif
| {{ __('Delivery') }} | | {{ (float) $order->delivery_price > 0 ? Money::rub($order->delivery_price) : __('Free') }} |
| **{{ __('Total') }}** | | **{{ Money::rub($order->total) }}** |
</x-mail::table>

**{{ __('Delivery') }}:** {{ $order->delivery_method->label() }}, {{ $order->addressLine() }}@if ($order->accessLine()), {{ $order->accessLine() }}@endif

**{{ __('Payment') }}:** {{ $order->payment_method->label() }}@if ($order->paymentStatusLabel()) ({{ mb_strtolower($order->paymentStatusLabel()) }})@endif

<x-mail::button :url="$url">
{{ __('View order') }}
</x-mail::button>

{{ __('Questions? Call :phone or reply to this email.', ['phone' => config('shop.phone')]) }}

{{ config('shop.name') }}
</x-mail::message>
