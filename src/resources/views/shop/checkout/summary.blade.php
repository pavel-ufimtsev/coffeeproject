@php
    use App\Support\Money;
    $promoError = $promoError ?? $errors->promo->first('promo');
@endphp
<ul class="summary-lines">
    @foreach ($quote->lines as $line)
        <li class="summary-line">
            <span class="summary-line__media">
                @if ($line->product()->mainImage)
                    <img src="{{ $line->product()->mainImage->thumbUrl() }}" alt="" width="64" height="64">
                @endif
                <span class="summary-line__qty" aria-label="{{ __('Quantity') }}">{{ $line->quantity }}</span>
            </span>
            <span class="summary-line__info">
                <span class="summary-line__name">{{ $line->product()->name }}</span>
                <span class="summary-line__meta">{{ collect([$line->variant->label, $line->grindLabel()])->filter()->implode(' · ') }}</span>
            </span>
            <span class="summary-line__total">{{ Money::rub($line->total()) }}</span>
        </li>
    @endforeach
</ul>

@if ($quote->promo)
    <div class="promo-applied">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
        <span class="promo-applied__code">{{ $quote->promo->code }}</span>
        <form method="POST" action="{{ route('checkout.promo.remove') }}" data-promo-form>
            @csrf
            @method('DELETE')
            <button type="submit" class="promo-applied__remove">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                <span class="visually-hidden">{{ __('Remove promo code') }}</span>
            </button>
        </form>
    </div>
@else
    <form method="POST" action="{{ route('checkout.promo.apply') }}" class="promo-form" data-promo-form novalidate>
        @csrf
        <div class="promo-form__row">
            <div class="float-field" data-field>
                <input id="checkout-promo" name="promo" type="text" value="{{ old('promo') }}" placeholder=" " autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="50" data-msg-required="{{ __('Enter a promo code.') }}"
                    @if ($promoError) aria-invalid="true" aria-describedby="checkout-promo-error" @endif>
                <label for="checkout-promo">{{ __('Promo code') }}</label>
            </div>
            <button type="submit" class="button button--secondary promo-form__apply">{{ __('Apply') }}</button>
        </div>
        <p class="float-field__error" id="checkout-promo-error" role="alert" @unless ($promoError) hidden @endunless>{{ $promoError }}</p>
        @if (! $promoError && ($problem = $checkout->promoProblem()))
            <p class="float-field__error" role="alert">{{ __('Promo code :code no longer applies: :reason', ['code' => $checkout->promoCode(), 'reason' => mb_strtolower(rtrim($problem, '.'))]) }}</p>
        @endif
    </form>
@endif

<dl class="summary-totals">
    <div>
        <dt>{{ __('Goods · :count pcs.', ['count' => $quote->count()]) }}</dt>
        <dd>{{ Money::rub($quote->subtotal) }}</dd>
    </div>
    @if ($quote->discount > 0)
        <div class="summary-totals__discount">
            <dt>{{ __('Promo code discount') }}</dt>
            <dd>−{{ Money::rub($quote->discount) }}</dd>
        </div>
    @endif
    <div>
        <dt>{{ __('Delivery') }}</dt>
        <dd>{{ $quote->deliveryPrice > 0 ? Money::rub($quote->deliveryPrice) : __('Free') }}</dd>
    </div>
    <div class="summary-totals__total">
        <dt>{{ __('Total') }}</dt>
        <dd>{{ Money::rub($quote->total()) }}</dd>
    </div>
</dl>
