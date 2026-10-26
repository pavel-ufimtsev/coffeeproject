@php use App\Support\Money; @endphp
<x-help-page :title="'Доставка кофе '.$city['to']" :crumbs="[['name' => __('Delivery and payment'), 'url' => route('help.delivery')]]" :wide="true"
    :description="'Доставка свежеобжаренного кофе '.$city['to'].($rate ? ': от '.Money::rub(ceil($rate->price)).', '.$rate->days() : '').'. Пункты выдачи и постаматы СДЭК'.($hasCourier ? ' и курьер до двери' : '').'.'"
    :lead="'Кофе обжаривают под заказ в Екатеринбурге и отправляют '.$city['to'].($hasCourier ? ' курьером или в пункты выдачи СДЭК.' : ' через СДЭК: в пункт выдачи или постамат рядом с домом.')">

    <dl class="city-facts">
        @if ($rate)
            <div>
                <dt>Срок в пути</dt>
                <dd>{{ $rate->days() }}</dd>
            </div>
            <div>
                <dt>Доставка пачки 250 г</dt>
                <dd>от {{ Money::rub(ceil($rate->price)) }}</dd>
            </div>
        @endif
        @if ($points)
            <div>
                <dt>Пунктов выдачи СДЭК</dt>
                <dd>{{ $points }}</dd>
            </div>
        @endif
        @if ($cdek['free_from'] ?? null)
            <div>
                <dt>Бесплатная доставка</dt>
                <dd>от {{ Money::rub($cdek['free_from']) }}</dd>
            </div>
        @endif
        @if ($hasCourier && $courier)
            <div>
                <dt>Курьер до двери</dt>
                <dd>{{ $courier['price'] > 0 ? Money::rub($courier['price']) : 'бесплатно' }}</dd>
            </div>
        @endif
    </dl>
    @unless ($rate)
        <p class="help-note">Стоимость и срок доставки {{ $city['to'] }} сайт покажет при оформлении заказа, когда вы выберете пункт выдачи на карте.</p>
    @endunless

    <section class="help-section" aria-labelledby="how-title">
        <h2 id="how-title">Как заказать</h2>
        <ol class="steps">
            <li><strong>Выберите кофе и помол.</strong> Смелем бесплатно под ваш способ заваривания или пришлём в зёрнах.</li>
            <li><strong>Укажите способ доставки.</strong> {{ $hasCourier ? 'Курьер привезёт заказ по адресу, а СДЭК — в удобный пункт выдачи.' : 'На карте выберите пункт выдачи или постамат СДЭК, сайт сразу покажет цену и срок.' }}</li>
            <li><strong>Оплатите заказ.</strong> Картой, через СБП или T-Pay на защищённой странице Т-Банка{{ $hasCourier && in_array(\App\Enums\PaymentMethod::Cash, $payments, true) ? '; курьеру можно заплатить при получении' : '' }}.</li>
            <li><strong>Получите кофе.</strong> {{ $hasCourier ? 'Курьер свяжется с вами перед доставкой, а СДЭК пришлёт сообщение, когда посылка будет в пункте.' : 'СДЭК пришлёт сообщение с кодом, когда посылка будет в пункте выдачи.' }}</li>
        </ol>
        @if ($roastDays !== '')
            <p class="help-note">Дни обжарки: {{ $roastDays }}. Срок в пути считается с момента отправки после обжарки.</p>
        @endif
    </section>

    @if ($featured->isNotEmpty())
        <section class="help-section" aria-labelledby="popular-title">
            <h2 id="popular-title">Чаще всего заказывают</h2>
            <div class="product-grid">
                @foreach ($featured as $product)
                    <x-catalog.product-card :product="$product" :heading-level="3" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="help-section" aria-labelledby="other-title">
        <h2 id="other-title">Доставка в другие города</h2>
        <ul class="chips">
            @foreach ($others as $otherSlug => $other)
                <li><a class="chip" href="{{ route('help.delivery.city', $otherSlug) }}">{{ $other['name'] }}</a></li>
            @endforeach
        </ul>
    </section>
</x-help-page>
