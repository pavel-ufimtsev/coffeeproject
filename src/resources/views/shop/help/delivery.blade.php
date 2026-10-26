@php use App\Support\Money; @endphp
<x-help-page :title="__('Delivery and payment')" :wide="true"
    description="Доставка кофе Coffee Project: курьер по Екатеринбургу, пункты выдачи и постаматы СДЭК по всей России. Сроки и стоимость по городам, способы оплаты."
    lead="Отправляем кофе из Екатеринбурга по всей России. Кофе обжаривают под заказ, поэтому посылка уходит после ближайшего дня обжарки и приезжает свежей.">

    <section class="help-section" aria-labelledby="ways-title">
        <h2 id="ways-title">Как можно получить заказ</h2>
        <div class="ways">
            @if ($courier)
                <div class="way">
                    <h3>Курьером по Екатеринбургу</h3>
                    <p class="way__price">{{ $courier['price'] > 0 ? Money::rub($courier['price']) : 'Бесплатно' }}</p>
                    <ul>
                        @if ($courier['free_from'] && $courier['price'] > 0)
                            <li>Бесплатно при заказе от {{ Money::rub($courier['free_from']) }}</li>
                        @endif
                        <li>Доставка до двери по адресу, который вы укажете</li>
                        @if (in_array(\App\Enums\PaymentMethod::Cash, $payments, true))
                            <li>Можно оплатить курьеру наличными или картой</li>
                        @endif
                    </ul>
                </div>
            @endif
            @if ($cdek)
                <div class="way">
                    <h3>Пункты выдачи и постаматы СДЭК</h3>
                    <p class="way__price">по тарифу СДЭК</p>
                    <ul>
                        @if ($cdek['free_from'])
                            <li>Бесплатно при заказе от {{ Money::rub($cdek['free_from']) }}</li>
                        @endif
                        <li>Тысячи пунктов по всей России, выбираете на карте при оформлении</li>
                        <li>Стоимость зависит от города и веса, точная сумма видна до оплаты</li>
                        <li>Оплата картой онлайн при оформлении</li>
                    </ul>
                </div>
            @endif
        </div>
        @if ($roastDays !== '')
            <p class="help-note">Дни обжарки: {{ $roastDays }}. Заказ, оформленный до дня обжарки, уходит сразу после него.</p>
        @endif
    </section>

    @if ($cdek)
        <section class="help-section" aria-labelledby="cities-title">
            <h2 id="cities-title">Сроки и цены по городам</h2>
            <p class="muted">Доставка одной пачки 250 г до пункта выдачи СДЭК. Цены обновляются каждый день по данным СДЭК.</p>
            <div class="table-scroll">
                <table class="delivery-table">
                    <thead>
                        <tr>
                            <th scope="col">Город</th>
                            <th scope="col">Срок в пути</th>
                            <th scope="col">Стоимость</th>
                            <th scope="col">Пунктов выдачи</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cities as $city)
                            <tr>
                                <th scope="row"><a href="{{ route('help.delivery.city', $city['slug']) }}">{{ $city['name'] }}</a></th>
                                <td>{{ $city['rate']?->days() ?? '—' }}</td>
                                <td>{{ $city['rate'] ? 'от '.Money::rub(ceil($city['rate']->price)) : '—' }}</td>
                                <td>{{ $city['points'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="help-note">Вашего города нет в списке? Мы доставляем в любой город, где есть СДЭК. Найдите пункт на карте при оформлении заказа, и сайт сразу покажет цену и срок.</p>
        </section>
    @endif

    <section class="help-section" aria-labelledby="pay-title">
        <h2 id="pay-title">Оплата</h2>
        <ul class="help-list">
            @if (in_array(\App\Enums\PaymentMethod::TBank, $payments, true))
                <li><strong>Картой или через СБП на сайте.</strong> Оплата проходит на защищённой странице Т-Банка, данные карты мы не видим и не храним. Можно оплатить банковской картой, через СБП или T-Pay.</li>
            @endif
            @if (in_array(\App\Enums\PaymentMethod::Cash, $payments, true))
                <li><strong>Курьеру при получении</strong> наличными или картой, только для курьерской доставки по Екатеринбургу.</li>
            @endif
            <li><strong>Для компаний</strong> выставляем счёт. Напишите нам через <a href="{{ route('wholesale') }}">страницу для бизнеса</a>.</li>
        </ul>
    </section>
</x-help-page>
