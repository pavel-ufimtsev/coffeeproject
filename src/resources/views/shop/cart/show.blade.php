<x-layouts.shop :title="__('Cart')" :meta="['robots' => 'noindex,follow']">
    <div class="container cart-page">
        <h1>{{ __('Cart') }}</h1>
        <div class="cart">
            @include('shop.cart.contents')
        </div>
    </div>
</x-layouts.shop>
