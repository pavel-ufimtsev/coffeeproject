@php
    use App\Enums\DeliveryMethod;
    use App\Support\Money;
    $courier = $checkout->courier();
    $cdek = $checkout->cdek();
    $methods = $checkout->deliveryMethods();
    $isCdek = $quote->deliveryMethod === DeliveryMethod::Cdek;
    $courierPrice = $courier->cost($quote->goods());
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
            <span class="choice__price">{{ $courierPrice > 0 ? Money::rub($courierPrice) : __('Free') }}</span>
        </label>
    @endif
    @if (in_array(DeliveryMethod::Cdek, $methods, true))
        <label class="choice">
            <input type="radio" name="delivery_method" value="{{ DeliveryMethod::Cdek->value }}" @checked($isCdek) data-delivery-method>
            <span class="choice__body">
                <span class="choice__title">{{ __('CDEK across Russia') }}</span>
                <span class="choice__note">
                    @if ($cdek->freeFrom() !== null)
                        {{ __('Pickup points and parcel lockers. Card payment online. Free from :amount.', ['amount' => Money::rub($cdek->freeFrom())]) }}
                    @else
                        {{ __('Pickup points and parcel lockers. Card payment online.') }}
                    @endif
                </span>
            </span>
            @if ($isCdek && $quote->deliveryReady())
                <span class="choice__price">{{ $quote->deliveryPrice > 0 ? Money::rub($quote->deliveryPrice) : __('Free') }}</span>
            @endif
        </label>
        @if ($isCdek)
            <div class="pickup-point" data-pickup-point>
                @if ($quote->pickupPoint)
                    <svg class="pickup-point__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <div class="pickup-point__body">
                        <p class="pickup-point__title">{{ $quote->pickupPoint->kind() }} · {{ $quote->pickupPoint->name }}</p>
                        <p>{{ $quote->pickupPoint->city }}, {{ $quote->pickupPoint->address }}</p>
                        @if ($quote->rate?->days())
                            <p class="pickup-point__note">{{ __('Delivery :days after shipping', ['days' => $quote->rate->days()]) }}</p>
                        @endif
                    </div>
                    <button type="button" class="button button--secondary pickup-point__change" data-pickup-point-open>{{ __('Change') }}</button>
                @else
                    <div class="pickup-point__body">
                        <p>{{ $quote->deliveryProblem }}</p>
                    </div>
                    <button type="button" class="button pickup-point__change" data-pickup-point-open>{{ __('Choose a pickup point') }}</button>
                @endif
            </div>
            @error('pickup_point')
                <p class="float-field__error" role="alert">{{ $message }}</p>
            @enderror
        @endif
    @else
        <div class="choice choice--disabled" aria-disabled="true">
            <span class="choice__radio" aria-hidden="true"></span>
            <span class="choice__body">
                <span class="choice__title">{{ __('CDEK across Russia') }}</span>
                <span class="choice__note">{{ __('Pickup points and parcel lockers') }}</span>
            </span>
            <span class="choice__badge">{{ __('Soon') }}</span>
        </div>
    @endif
</div>

@if ($methods === [])
    <p class="notice notice--warning">{{ __('Delivery is not available right now. Please call us at :phone to order.', ['phone' => config('shop.phone')]) }}</p>
@elseif ($quote->remainingForFreeDelivery !== null && $quote->freeDeliveryFrom)
    @php($progress = max(4, min(100, round(100 * $quote->goods() / $quote->freeDeliveryFrom))))
    <div class="free-delivery">
        <p>{!! __('Add :amount more for <strong>free delivery</strong>', ['amount' => e(Money::rub($quote->remainingForFreeDelivery))]) !!}</p>
        <span class="free-delivery__bar" style="--progress: {{ $progress }}%" aria-hidden="true"><span></span></span>
    </div>
@elseif ($quote->deliveryReady() && $quote->deliveryPrice <= 0 && $quote->freeDeliveryFrom !== null)
    <p class="free-delivery free-delivery--done">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
        {{ __('Delivery is free for this order') }}
    </p>
@endif
