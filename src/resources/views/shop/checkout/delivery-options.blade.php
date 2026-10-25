@php
    use App\Enums\DeliveryMethod;
    use App\Support\Money;
    $courier = $checkout->courier();
    $methods = $checkout->deliveryMethods();
@endphp
<div class="choice-list" role="radiogroup" aria-label="{{ __('Delivery method') }}">
    @if (in_array(DeliveryMethod::Courier, $methods, true))
        <label class="choice">
            <input type="radio" name="delivery_method" value="{{ DeliveryMethod::Courier->value }}" @checked($quote->deliveryMethod === DeliveryMethod::Courier) data-delivery-method>
            <span class="choice__body">
                <span class="choice__title">{{ __('Courier in Ekaterinburg') }}</span>
                <span class="choice__note">
                    @if ($courier->freeFrom() !== null && $courier->price() > 0)
                        {{ __('We will call to agree on a time. Free from :amount.', ['amount' => Money::rub($courier->freeFrom())]) }}
                    @else
                        {{ __('We will call to agree on a time.') }}
                    @endif
                </span>
            </span>
            <span class="choice__price">{{ $quote->deliveryPrice > 0 ? Money::rub($quote->deliveryPrice) : __('Free') }}</span>
        </label>
    @endif
    <div class="choice choice--disabled" aria-disabled="true">
        <span class="choice__radio" aria-hidden="true"></span>
        <span class="choice__body">
            <span class="choice__title">{{ __('CDEK across Russia') }}</span>
            <span class="choice__note">{{ __('Pickup points and CDEK courier') }}</span>
        </span>
        <span class="choice__badge">{{ __('Soon') }}</span>
    </div>
</div>

@if ($methods === [])
    <p class="notice notice--warning">{{ __('Delivery is not available right now. Please call us at :phone to order.', ['phone' => config('shop.phone')]) }}</p>
@elseif ($quote->remainingForFreeDelivery !== null)
    @php($progress = max(4, min(100, round(100 * $quote->goods() / $courier->freeFrom()))))
    <div class="free-delivery">
        <p>{!! __('Add :amount more for <strong>free delivery</strong>', ['amount' => e(Money::rub($quote->remainingForFreeDelivery))]) !!}</p>
        <span class="free-delivery__bar" style="--progress: {{ $progress }}%" aria-hidden="true"><span></span></span>
    </div>
@elseif ($quote->deliveryPrice <= 0 && $courier->price() > 0)
    <p class="free-delivery free-delivery--done">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
        {{ __('Delivery is free for this order') }}
    </p>
@endif
