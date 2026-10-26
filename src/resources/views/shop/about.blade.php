@php
    use App\Support\OrganizationSchema;

    // From the old site, the Yii code and press coverage in the web archive.
    $timeline = [
        ['year' => '2012', 'title' => 'Первая кофейня', 'text' => 'Александра Уфимцева вместе с братом открывает Coffee Project в бизнес-центре «Суворов» на Радищева, 6а. Одними из первых в Екатеринбурге мы варим кофе «альтернативой»: в харио, аэропрессе и кемексе, а не только в кофемашине.'],
        ['year' => '2013', 'title' => 'Своя обжарка', 'text' => 'Открываем обжарочный цех, первый в Екатеринбурге. Зерно на рынке попадалось пережаренное и старое, и мы решили жарить сами. Цех начинал в Большом Истоке, а потом переехал в центр, ближе к кофейне.'],
        ['year' => '2016', 'title' => 'Кофейные паспорта и интернет-магазин', 'text' => 'Цех обжаривает около четырёх тонн зерна в месяц, «Деловой квартал» называет его одним из крупнейших на Урале. Для каждого сорта мы ведём кофейный паспорт. В том же году запускаем coffeeproject.ru, чтобы свежий кофе можно было заказать домой.'],
        ['year' => '2017', 'title' => 'Кофейни и кофе с собой', 'text' => 'Открываем кофейню в Gastro Market универмага Bolshoy на Малышева, 71 и развиваем сеть кофеен с собой. Наш кофе варят кофейни-партнёры в разных городах России и Казахстана.'],
        ['year' => '2018', 'title' => 'Кофе по всей России', 'text' => 'Отправляем кофе в Москву, Санкт-Петербург, Новосибирск, Челябинск, Пермь и Тюмень. В ассортименте 20 сортов, а самой любимой у покупателей остаётся смесь Sweet Chocolate.'],
        ['year' => 'Дальше', 'title' => 'Только кофе', 'text' => 'Кофейни — это прежде всего кухня, аренда и смены, а нам всегда больше всего нравилось само зерно. Мы передали кофейни новым владельцам (сейчас они работают уже не под нашим брендом) и сосредоточились на кофе для дома и офиса.'],
        ['year' => '2026', 'title' => 'Новый coffeeproject.ru', 'text' => 'Перезапускаем магазин: удобный каталог по способу заваривания, оплата картой и СБП, курьер по Екатеринбургу и пункты выдачи СДЭК по всей стране. Кофе по-прежнему обжаривают под заказ в Екатеринбурге.'],
    ];

    $press = [
        ['date' => '2018-02-08', 'outlet' => 'Деловой квартал', 'title' => 'Кофейный бизнес Урала: «Людям интересны простые вещи: какой кофе выбрать, как приготовить»', 'url' => 'http://ekb.dk.ru/news/kofeynyy-biznes-urala-lyudyam-interesny-prostye-veschi-kakoy-kofe-vybrat-kak-prigotovit-237098973'],
        ['date' => '2018-01-29', 'outlet' => 'Моменты', 'title' => '«Уехать в Москву всегда можно, но такой цели нет». Истории бариста, которые заставят вас любить Екатеринбург', 'url' => 'https://momenty.org/people/i180802/'],
        ['date' => '2018-01-13', 'outlet' => 'BroDude', 'title' => 'Несколько сортов кофе, которые ты должен попробовать', 'url' => 'https://brodude.ru/neskolko-sortov-kofe-kotorye-ty-dolzhen-poprobovat/'],
        ['date' => '2017-04-26', 'outlet' => 'Resto.ru', 'title' => 'Coffee Project: завтрак с видом на единорогов', 'url' => 'https://ekb.resto.ru/news/2007629'],
        ['date' => '2017-04-25', 'outlet' => 'The Village', 'title' => 'Новая Sufra со шведским столом и Coffee Project в «Большом»', 'url' => 'http://www.the-village.ru/village/food/opening/264042-sufra-and-coffee-project'],
        ['date' => '2017-03-30', 'outlet' => 'It’s My City', 'title' => '«Мы хотим сделать качественный кофе доступным для каждого»', 'url' => 'http://itsmycity.ru/blog/post/id/7052'],
        ['date' => '2016-11-02', 'outlet' => 'Деловой квартал', 'title' => 'Истина в зерне: как работает самый большой на Урале цех по обжарке кофе', 'url' => 'http://ekb.dk.ru/news/istina-v-zerne-kak-rabotaet-samyy-bolshoy-na-urale-tseh-po-obzharke-kofe-237054064/'],
        ['date' => '2016-05-11', 'outlet' => 'Четвёртый канал', 'title' => 'Кофе в турке: рецепт в рубрике «Скоро завтрак»', 'url' => 'http://www.channel4.ru/stories/10325/'],
        ['date' => '2016-04-29', 'outlet' => 'Уральский студент', 'title' => 'Кофейные пути Екатеринбурга', 'url' => 'http://www.uralstudent.ru/articles/kultura-i-otdyih/2248365/'],
        ['date' => '2016-02-04', 'outlet' => 'NDNews', 'title' => '«Мне латте, только без молока!»: как Россия становится «кофейной» страной', 'url' => null],
    ];

    $jsonLd = [OrganizationSchema::page('AboutPage', 'О компании Coffee Project', route('about'))];
@endphp
<x-layouts.shop :meta="[
    'title' => 'О компании Coffee Project: свежеобжаренный кофе из Екатеринбурга с 2012 года',
    'description' => 'Coffee Project с 2012 года: от первой кофейни и первой обжарки в Екатеринбурге до магазина свежеобжаренного кофе с доставкой по России.',
    'canonical' => route('about'),
    'image' => asset('images/about/cups.webp'),
]" :json-ld="$jsonLd" class="site-main--flush">
    <section class="about-hero">
        <div class="container about-hero__inner">
            <div class="about-hero__text">
                <p class="eyebrow">{{ __('About us') }}</p>
                <h1>Свежеобжаренный кофе из Екатеринбурга с&nbsp;2012 года</h1>
                <p class="about-hero__lead">
                    Coffee Project начинался в 2012 году с одной кофейни, а через год у нас появилась
                    первая в Екатеринбурге своя обжарка. Потом мы выбрали главное: кофе для дома.
                    Сегодня кофе для нас обжаривают под заказ в Екатеринбурге, а мы отправляем его
                    по всей России, чтобы хороший кофе был доступен каждому.
                </p>
                <p class="about-hero__actions">
                    <a href="{{ url('/coffee') }}" class="button button--large">{{ __('Choose coffee') }}</a>
                    <a href="{{ route('contacts') }}" class="button button--large button--secondary">{{ __('Contact us') }}</a>
                </p>
            </div>
            <figure class="about-hero__photo">
                <img src="/images/about/cups.webp" width="1200" height="800" alt="Два стакана кофе Coffee Project на кофемашине" fetchpriority="high">
            </figure>
        </div>
    </section>

    <section class="container about-facts" aria-label="Coffee Project в цифрах">
        <dl class="facts">
            <div class="fact">
                <dt>Год первой кофейни</dt>
                <dd>2012</dd>
            </div>
            <div class="fact">
                <dt>Первая обжарка в Екатеринбурге</dt>
                <dd>2013</dd>
            </div>
            <div class="fact">
                <dt>Тонны кофе в месяц уже в 2016 году</dt>
                <dd>≈4</dd>
            </div>
            <div class="fact">
                <dt>Доставка</dt>
                <dd>вся Россия</dd>
            </div>
        </dl>
    </section>

    <section class="container about-section" aria-labelledby="story-title">
        <header class="about-section__header">
            <p class="eyebrow">Наша история</p>
            <h2 id="story-title">От кофейни на Радищева до кофе по всей стране</h2>
        </header>
        <ol class="timeline">
            @foreach ($timeline as $step)
                <li class="timeline__item">
                    @if (is_numeric($step['year']))
                        <p class="timeline__year"><time datetime="{{ $step['year'] }}">{{ $step['year'] }}</time></p>
                    @else
                        <p class="timeline__year timeline__year--word">{{ $step['year'] }}</p>
                    @endif
                    <div class="timeline__body">
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="about-band" aria-labelledby="roasting-title">
        <div class="container about-split">
            <div class="about-split__text prose">
                <p class="eyebrow">Чему нас научила своя обжарка</p>
                <h2 id="roasting-title">Кофейный паспорт для каждого сорта</h2>
                <p>
                    Кофе — сельскохозяйственный продукт, и от урожая к урожаю он меняется. Чтобы вкус оставался
                    стабильным, на нашей обжарке для каждого сорта завели кофейный паспорт: происхождение и марка
                    зелёного зерна, профиль обжарки (температура и время) и вкусовые характеристики, с которыми
                    сверялась каждая новая партия. Годы у ростера научили нас главному:
                </p>
                <ul class="checklist">
                    <li><strong>Всё начинается с зелёного зерна.</strong> Как оно хранилось и перевозилось, видно ещё до обжарки, и хорошим профилем плохое зерно не исправить.</li>
                    <li><strong>Обжарка — ручная работа.</strong> Ростеру помогает автоматика, но профиль под сорт подбирает и ведёт человек.</li>
                    <li><strong>Вкус проверяют на каппинге.</strong> Не по описанию на пачке, а в чашке, сравнивая с эталоном.</li>
                    <li><strong>Кофе должен быть свежим.</strong> Поэтому кофе обжаривают под заказ: он не лежит на складе, а уезжает к вам.</li>
                </ul>
            </div>
            <figure class="about-split__photo">
                <img src="/images/about/coffee-passports.webp" width="1200" height="1038" alt="Этикетки сортов Coffee Project: Панама Бамбито, Сальвадор Санта-Барбара, Тарразу и другие" loading="lazy">
                <figcaption>Этикетки сортов с нашей обжарки: у каждого был свой паспорт и профиль.</figcaption>
            </figure>
        </div>
    </section>

    <section class="container about-section" aria-labelledby="freshness-title">
        <div class="about-split about-split--reverse">
            <div class="about-split__text prose">
                <p class="eyebrow">Свежесть</p>
                <h2 id="freshness-title">Почему мы говорим о дне обжарки</h2>
                <p>
                    Только что обжаренное зерно пахнет не кофе, а свежей выпечкой: аромат появляется через сутки.
                    Дальше кофе «отдыхает», и примерно с пятого по четырнадцатый день вкус раскрывается полнее всего;
                    точный срок зависит от сорта и степени обжарки.
                </p>
                <p>
                    По ГОСТу кофе в зёрнах можно хранить полтора года, но уже через месяц он теряет заметную часть
                    аромата. Поэтому мы не держим запасов обжаренного зерна: кофе обжаривают под ваши заказы,
                    и он уезжает к вам свежим.
                </p>
                <p><a href="{{ url('/coffee') }}">Посмотреть кофе в каталоге</a></p>
            </div>
            <figure class="about-split__photo">
                <img src="/images/about/coffee-shop.webp" width="720" height="480" alt="Зал первой кофейни Coffee Project в бизнес-центре «Суворов»" loading="lazy">
                <figcaption>Первая кофейня Coffee Project в БЦ «Суворов», Радищева, 6а. С неё всё началось.</figcaption>
            </figure>
        </div>
    </section>

    <section class="container about-section" aria-labelledby="values-title">
        <header class="about-section__header">
            <p class="eyebrow">Во что мы верим</p>
            <h2 id="values-title">В создании идеального кофе не бывает мелочей</h2>
        </header>
        <ul class="values">
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c3 4 6 6.5 6 10a6 6 0 0 1-12 0c0-3.5 3-6 6-10Z"/></svg>
                <h3>Свежесть</h3>
                <p>Первое правило нашей команды с первого дня: кофе должен быть свежим. Поэтому его обжаривают под заказ, а не на склад.</p>
            </li>
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12l2 2 4-4"/><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3Z"/></svg>
                <h3>Стабильное качество</h3>
                <p>Привычка из своей обжарки: любимый сорт должен быть одинаково вкусным от пачки к пачке.</p>
            </li>
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19h16M6 15h12l-1 4H7l-1-4Z"/><path d="M9 11V5m3 6V3m3 8V6"/></svg>
                <h3>Хороший кофе для каждого</h3>
                <p>Честные цены без итальянской наценки: свежая российская обжарка вкуснее и доступнее старого импортного зерна.</p>
            </li>
            <li class="value">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/><path d="M8.5 11h7M8.5 14h4"/></svg>
                <h3>Поможем с выбором</h3>
                <p>За плечами годы за стойкой кофеен. Подскажем сорт, помол и рецепт, чтобы кофе получился дома не хуже, чем в кофейне.</p>
            </li>
        </ul>
    </section>

    <section class="container about-section" aria-labelledby="people-title">
        <header class="about-section__header">
            <p class="eyebrow">Кто мы</p>
            <h2 id="people-title">Люди за Coffee Project</h2>
            <p class="about-section__lead">Семейная компания: Coffee Project основали брат и сестра. За эти годы с нами работали бариста, тренеры и ростмастера, и их опыт до сих пор в каждой нашей рекомендации.</p>
        </header>
        <ul class="people">
            <li class="person">
                <span class="person__avatar" aria-hidden="true">АУ</span>
                <div>
                    <h3>Александра Уфимцева</h3>
                    <p class="person__role">Сооснователь, коммерческий директор</p>
                    <p>Вместе с братом открыла первую кофейню Coffee Project в 2012 году. Отвечает за ассортимент, партнёров и работу с покупателями.</p>
                </div>
            </li>
            <li class="person">
                <span class="person__avatar" aria-hidden="true">ДР</span>
                <div>
                    <h3>Дарья Рядозубова</h3>
                    <p class="person__role">Шеф-бариста и тренер бариста кофеен Coffee Project</p>
                    <p>Обучала бариста наших кофеен и рассказывала в эфирах и интервью, как приготовить дома капучино не хуже, чем в кофейне.</p>
                </div>
            </li>
            <li class="person">
                <span class="person__avatar" aria-hidden="true">АП</span>
                <div>
                    <h3>Алексей Платонов</h3>
                    <p class="person__role">Ростмастер обжарочного цеха Coffee Project</p>
                    <p>Вёл кофейные паспорта и обжарку, проводил каппинги для гостей, бариста и оптовых клиентов.</p>
                </div>
            </li>
        </ul>
    </section>

    <section class="about-band" aria-labelledby="press-title">
        <div class="container about-section about-section--flush">
            <header class="about-section__header">
                <p class="eyebrow">Пресса</p>
                <h2 id="press-title">О нас писали</h2>
            </header>
            <blockquote class="about-quote">
                <p>«Coffee Project» называют законодателями кофейной моды на Урале. Команда проекта первой открыла в Екатеринбурге собственное обжарочное производство и ввела в меню «альтернативу».</p>
                <footer>— <cite>Деловой квартал</cite>, 8 февраля 2018</footer>
            </blockquote>
            <ul class="press">
                @foreach ($press as $item)
                    <li class="press__item">
                        <p class="press__meta">
                            <span class="press__outlet">{{ $item['outlet'] }}</span>
                            <time datetime="{{ $item['date'] }}">{{ \Illuminate\Support\Carbon::parse($item['date'])->locale('ru')->translatedFormat('j F Y') }}</time>
                        </p>
                        @if ($item['url'])
                            <a class="press__title" href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['title'] }}</a>
                        @else
                            <p class="press__title">{{ $item['title'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p class="muted press__note">Некоторые публикации старые, и издания могли перенести или удалить страницы.</p>
        </div>
    </section>

    <section class="container about-section" aria-labelledby="company-title">
        <div class="about-company">
            <div>
                <p class="eyebrow">Реквизиты</p>
                <h2 id="company-title">Кто продаёт кофе на этом сайте</h2>
                <p class="about-section__lead">Магазин coffeeproject.ru работает от имени нашей компании. За каждый заказ мы отвечаем сами: от выбора сорта до доставки.</p>
                <p class="about-hero__actions">
                    <a href="{{ route('contacts') }}" class="button">{{ __('Contact us') }}</a>
                    <a href="{{ route('legal.terms') }}" class="button button--secondary">{{ __('Terms of use') }}</a>
                </p>
            </div>
            <dl class="requisites">
                <div><dt>Компания</dt><dd>{{ config('shop.legal_full_name') }}</dd></div>
                <div><dt>ОГРН</dt><dd>{{ config('shop.ogrn') }}</dd></div>
                <div><dt>ИНН / КПП</dt><dd>{{ config('shop.inn') }} / {{ config('shop.kpp') }}</dd></div>
                <div><dt>Адрес</dt><dd>{{ config('shop.legal_address') }}</dd></div>
                <div><dt>Телефон</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a></dd></div>
                <div><dt>Почта</dt><dd><a href="mailto:{{ config('shop.email') }}">{{ config('shop.email') }}</a></dd></div>
            </dl>
        </div>
    </section>
</x-layouts.shop>
