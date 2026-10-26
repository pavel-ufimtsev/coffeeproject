@php
    use App\Support\Money;

    $cell = 'padding: 12px 0; border-bottom: 1px solid #eaeaea; vertical-align: top;';
    $sum = 'padding: 4px 0; vertical-align: top;';
@endphp
<x-mail.layout :heading="$texts['heading']" :preheader="$texts['intro_text']">
    <div class="text">{{ $texts['intro'] }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 28px; font-size: 15px; line-height: 1.45;">
        @foreach ($order->items as $item)
            <tr>
                <td style="{{ $cell }}">
                    <span style="font-weight: 700; color: #151515;">{{ $item->name }}</span>
                    @if ($item->meta())
                        <br><span style="font-size: 13px; color: #8a8a8a;">{{ $item->meta() }}</span>
                    @endif
                </td>
                <td style="{{ $cell }} padding-left: 12px; white-space: nowrap; color: #8a8a8a;" align="right">{{ $item->quantity }}&nbsp;×&nbsp;{{ Money::rub($item->price) }}</td>
                <td style="{{ $cell }} padding-left: 16px; white-space: nowrap; color: #151515;" align="right">{{ Money::rub($item->total) }}</td>
            </tr>
        @endforeach
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 12px; font-size: 15px; line-height: 1.45;">
        <tr>
            <td style="{{ $sum }}">{{ __('Goods') }}</td>
            <td style="{{ $sum }} white-space: nowrap;" align="right">{{ Money::rub($order->subtotal) }}</td>
        </tr>
        @if ((float) $order->discount > 0)
            <tr>
                <td style="{{ $sum }}">{{ __('Promo code :code', ['code' => $order->promo_code]) }}</td>
                <td style="{{ $sum }} white-space: nowrap; color: #2e7d32;" align="right">−{{ Money::rub($order->discount) }}</td>
            </tr>
        @endif
        <tr>
            <td style="{{ $sum }}">{{ __('Delivery') }}</td>
            <td style="{{ $sum }} white-space: nowrap;" align="right">{{ (float) $order->delivery_price > 0 ? Money::rub($order->delivery_price) : __('Free') }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 0 0; font-size: 18px; font-weight: 800; color: #151515;">{{ __('Total') }}</td>
            <td style="padding: 10px 0 0; font-size: 18px; font-weight: 800; color: #151515; white-space: nowrap;" align="right">{{ Money::rub($order->total) }}</td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 28px; background: #f3f2ef; border-radius: 12px; font-size: 15px; line-height: 1.5;">
        <tr>
            <td style="padding: 18px 20px;">
                <div style="font-size: 13px; color: #8a8a8a;">{{ __('Delivery') }}</div>
                <div style="color: #151515;">{{ $order->delivery_method->label() }}</div>
                <div>{{ $order->destinationLine() }}@if (! $order->pickup_point_code && $order->accessLine()), {{ $order->accessLine() }}@endif</div>

                <div style="font-size: 13px; color: #8a8a8a; margin-top: 14px;">{{ __('Payment') }}</div>
                <div style="color: #151515;">{{ $order->payment_method->label() }}@if ($order->paymentStatusLabel()) ({{ mb_strtolower($order->paymentStatusLabel()) }})@endif</div>

                <div style="font-size: 13px; color: #8a8a8a; margin-top: 14px;">{{ __('Recipient') }}</div>
                <div style="color: #151515;">{{ $order->customerName() }}, {{ $order->formattedPhone() }}</div>
                @if ($order->comment)
                    <div style="font-size: 13px; color: #8a8a8a; margin-top: 14px;">{{ __('Comment') }}</div>
                    <div>{{ $order->comment }}</div>
                @endif
            </td>
        </tr>
    </table>

    <x-mail.button :url="$url">{{ __('View order') }}</x-mail.button>

    <div class="text">{{ $texts['outro'] }}</div>
</x-mail.layout>
