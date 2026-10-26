@php
    $grindLabel = $grind ? (\App\Enums\ProductType::grinds()[$grind] ?? null) : null;
@endphp
<x-layouts.shop :meta="[
    'title' => 'Подобрать кофе за минуту: тест | '.config('shop.name'),
    'description' => 'Пять коротких вопросов о том, как вы варите и какой вкус любите, и мы подберём свежеобжаренный кофе, который вам понравится.',
    'canonical' => route('quiz'),
    'robots' => $answers ? 'noindex,follow' : 'index,follow',
]">
    <div class="container quiz-page">
        @if ($answers)
            <header class="quiz-page__header">
                <p class="eyebrow">{{ __('Your coffee') }}</p>
                <h1>Вот что мы советуем попробовать</h1>
                <p class="quiz-page__lead">Подобрали по вашим ответам из кофе, который сейчас в наличии.
                    @if ($grindLabel)
                        Помол уже выбран: <strong>{{ mb_strtolower($grindLabel) }}</strong>, смелем бесплатно.
                    @endif
                </p>
            </header>

            @if ($matches->isNotEmpty())
                <ol class="quiz-results">
                    @foreach ($matches as $match)
                        <li class="quiz-result">
                            @if ($loop->first)
                                <p class="quiz-result__badge">{{ __('Best match') }}</p>
                            @endif
                            <x-catalog.product-card :product="$match['product']" :heading-level="2" :grind="$grind" />
                            @if ($match['reasons'])
                                <ul class="quiz-result__reasons">
                                    @foreach ($match['reasons'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="notice">Сейчас нет кофе в наличии. Загляните чуть позже или <a href="{{ route('contacts') }}">напишите нам</a>.</p>
            @endif

            <p class="quiz-page__again">
                <a href="{{ route('quiz') }}" class="button button--secondary">{{ __('Take the quiz again') }}</a>
                <a href="{{ url('/coffee') }}" class="button">{{ __('All coffee') }}</a>
            </p>
        @else
            <form method="GET" action="{{ route('quiz') }}" class="quiz" data-quiz>
                <header class="quiz-page__header">
                    <p class="eyebrow">{{ __('Coffee quiz') }}</p>
                    <h1>Подберём кофе за минуту</h1>
                    <p class="quiz-page__lead">Пять коротких вопросов о том, как вы варите кофе и какой вкус любите.</p>
                </header>

                <div class="quiz__progress" aria-hidden="true" hidden data-quiz-progress><span></span></div>

                @foreach ($questions as $key => $question)
                    <fieldset class="quiz__step" data-quiz-step>
                        <legend>
                            <span class="quiz__count">{{ __('Question :n of :total', ['n' => $loop->iteration, 'total' => $loop->count]) }}</span>
                            <span class="quiz__title">{{ $question['title'] }}</span>
                        </legend>
                        <div class="quiz__options">
                            @foreach ($question['options'] as $value => $label)
                                <label class="quiz__option">
                                    <input type="radio" name="{{ $key }}" value="{{ $value }}" required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="quiz__hint">{{ $question['hint'] }}</p>
                        <div class="quiz__nav" hidden data-quiz-nav>
                            @unless ($loop->first)
                                <button type="button" class="link-button" data-quiz-back>{{ __('Back') }}</button>
                            @endunless
                        </div>
                    </fieldset>
                @endforeach

                <button type="submit" class="button button--large quiz__submit" data-quiz-submit>{{ __('Show my coffee') }}</button>
            </form>
        @endif
    </div>
</x-layouts.shop>
