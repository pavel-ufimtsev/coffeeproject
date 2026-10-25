@php
    use App\Services\Cart\Cart;
    use App\Support\Money;
@endphp
@if ($cart->isEmpty())
    <div class="cart__empty">
        <p>{{ __('Your cart is empty.') }}</p>
        <a href="{{ url('coffee') }}" class="button">{{ __('Choose coffee') }}</a>
    </div>
@else
    <ul class="cart__lines">
        @foreach ($cart->lines() as $line)
            <li class="cart-line" data-line="{{ $line->key }}" style="--i: {{ $loop->index }}">
                <a href="{{ url($line->product()->path()) }}" class="cart-line__media" tabindex="-1" aria-hidden="true">
                    @if ($line->product()->mainImage)
                        <img src="{{ $line->product()->mainImage->thumbUrl() }}" alt="" width="80" height="80">
                    @endif
                </a>
                <div class="cart-line__info">
                    <a href="{{ url($line->product()->path()) }}" class="cart-line__name">{{ $line->product()->name }}</a>
                    <p class="cart-line__meta">{{ collect([$line->variant->label, $line->grindLabel()])->filter()->implode(' · ') }}</p>
                    <div class="cart-line__controls">
                        <form method="POST" action="{{ route('cart.update', $line->key) }}" class="stepper" data-cart-update>
                            @csrf
                            @method('PATCH')
                            <button type="submit" name="quantity" value="{{ $line->quantity - 1 }}" class="stepper__button" aria-label="{{ __('Fewer') }}">−</button>
                            <span class="stepper__value" aria-label="{{ __('Quantity') }}">{{ $line->quantity }}</span>
                            <button type="submit" name="quantity" value="{{ $line->quantity + 1 }}" class="stepper__button" aria-label="{{ __('More') }}" @disabled($line->quantity >= Cart::MAX_QUANTITY)>+</button>
                        </form>
                        <form method="POST" action="{{ route('cart.destroy', $line->key) }}" data-cart-update>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-button cart-line__remove">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </div>
                <p class="cart-line__total">{{ Money::rub($line->total()) }}</p>
            </li>
        @endforeach
    </ul>

    <div class="cart__footer">
        <p class="cart__subtotal">
            <span>{{ __('Subtotal') }}</span>
            <strong>{{ Money::rub($cart->subtotal()) }}</strong>
        </p>
        <p class="cart__note">{{ __('Delivery is calculated at checkout.') }}</p>
        <a href="{{ route('checkout.show') }}" class="button button--block button--large">{{ __('Checkout') }}</a>
    </div>
@endif
