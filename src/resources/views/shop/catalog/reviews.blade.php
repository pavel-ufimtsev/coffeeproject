{{-- Approved reviews with a summary, and the form to leave one (checked by an admin first). --}}
@php
    $sent = session('review_sent');
    $count = $reviews->count();
    $shown = 6;
@endphp
<section class="product-section reviews" id="reviews" aria-labelledby="reviews-title">
    <div class="reviews__head">
        <h2 id="reviews-title">{{ __('Reviews') }}</h2>
        @if ($rating)
            <p class="reviews__score">
                <span class="stars" style="--rating: {{ $rating }}" aria-hidden="true"></span>
                <strong>{{ number_format($rating, 1, ',', '') }}</strong>
                <span class="muted">{{ trans_choice(':count review|:count reviews', $count, ['count' => $count]) }}</span>
            </p>
        @endif
    </div>

    @if ($count > 0)
        <ul class="review-list">
            @foreach ($reviews as $review)
                <li class="review" @if ($loop->index >= $shown) hidden data-review-more @endif>
                    <p class="review__meta">
                        <span class="stars stars--small" style="--rating: {{ $review->rating }}" role="img" aria-label="{{ __(':rating out of 5', ['rating' => $review->rating]) }}"></span>
                        <strong>{{ $review->name }}</strong>
                        @if ($review->is_verified_buyer)
                            <span class="review__badge">{{ __('Bought this coffee') }}</span>
                        @endif
                        <time class="muted" datetime="{{ ($review->approved_at ?? $review->created_at)->toDateString() }}">{{ ($review->approved_at ?? $review->created_at)->locale(app()->getLocale())->translatedFormat('j F Y') }}</time>
                    </p>
                    @if ($review->brew_method)
                        <p class="review__brew">{{ __('Brewed in: :method', ['method' => $review->brew_method]) }}</p>
                    @endif
                    <p class="review__body">{!! nl2br(e($review->body)) !!}</p>
                </li>
            @endforeach
        </ul>
        @if ($count > $shown)
            <button type="button" class="button button--secondary reviews__more" data-reviews-more>{{ __('Show all reviews (:count)', ['count' => $count]) }}</button>
        @endif
    @else
        <p class="muted">{{ __('No reviews yet. Tried this coffee? Tell others how it was.') }}</p>
    @endif

    <details class="review-form" @if ($sent || $errors->hasAny(['rating', 'body', 'name', 'email', 'consent'])) open @endif>
        <summary class="button button--secondary">{{ __('Write a review') }}</summary>

        <div class="contact-card review-form__card" data-contact>
            <div class="contact-thanks" role="status" tabindex="-1" data-contact-thanks @unless ($sent) hidden @endunless>
                <svg class="contact-thanks__icon" viewBox="0 0 52 52" width="56" height="56" aria-hidden="true"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M15 27l7 7 15-16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <p class="contact-thanks__title">{{ __('Review sent') }}</p>
                <p data-contact-thanks-text>{{ $sent }}</p>
            </div>

            <form method="POST" action="{{ route('reviews.store', $product) }}" class="contact-form" data-contact-form @if ($sent) hidden @endif
                data-msg-network="{{ __('Could not send the message. Check your connection and try again, or write to :email.', ['email' => config('shop.email')]) }}"
                data-msg-throttle="{{ __('Too many messages in a row. Please wait a minute and try again.') }}">
                @csrf
                <p class="contact-form__error" role="alert" data-contact-error hidden></p>

                <fieldset class="rate" data-field>
                    <legend>{{ __('Your rating') }}</legend>
                    <div class="rate__stars">
                        @foreach ([5, 4, 3, 2, 1] as $star)
                            <input type="radio" name="rating" value="{{ $star }}" id="rate-{{ $star }}" required @checked((int) old('rating') === $star)
                                data-msg-required="{{ __('Rate the coffee from one to five stars.') }}">
                            <label for="rate-{{ $star }}" title="{{ trans_choice(':count star|:count stars', $star, ['count' => $star]) }}">
                                <span class="visually-hidden">{{ trans_choice(':count star|:count stars', $star, ['count' => $star]) }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="float-field__error" id="rate-error" @unless ($errors->has('rating')) hidden @endunless>{{ $errors->first('rating') }}</p>
                </fieldset>

                <div class="contact-form__row">
                    <x-checkout.field name="name" id="review-name" :label="__('Your name')" required maxlength="60" autocomplete="given-name" :value="auth()->user()?->name"
                        :data-msg-required="__('Tell us your name.')" />
                    <x-checkout.field name="email" id="review-email" type="email" :label="__('Email, not shown on the site')" required maxlength="255" autocomplete="email" :value="auth()->user()?->email"
                        :data-msg-required="__('Enter your email. We will not show it on the site.')" :data-msg-invalid="__('Enter an email like name@example.ru.')" />
                </div>

                @if ($brewMethods->isNotEmpty())
                    <div class="field">
                        <label for="review-brew">{{ __('How did you brew it?') }} <span class="muted">({{ __('optional') }})</span></label>
                        <span class="select">
                            <select id="review-brew" name="brew_method">
                                <option value="">{{ __('Not specified') }}</option>
                                @foreach ($brewMethods as $method)
                                    <option @selected(old('brew_method') === $method)>{{ $method }}</option>
                                @endforeach
                            </select>
                        </span>
                    </div>
                @endif

                <div class="contact-form__message">
                    <x-checkout.field name="body" id="review-body" type="textarea" :label="__('What did you like or not like?')" required minlength="20" maxlength="3000"
                        :data-msg-required="__('Write a few words about the coffee.')" :data-msg-short="__('Tell us a little more, at least :min characters.', ['min' => 20])" />
                </div>

                <div class="contact-form__trap" aria-hidden="true">
                    <label for="review-website">Website</label>
                    <input type="text" id="review-website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <label class="checkout-consent" data-field>
                    <input type="checkbox" name="consent" value="1" required data-msg-required="{{ __('Please agree to the processing of your personal data.') }}">
                    <span>{!! __('I agree to the processing of my personal data under the <a href=":privacy" target="_blank">privacy policy</a>.', ['privacy' => route('legal.privacy')]) !!}</span>
                    <span class="float-field__error" id="review-consent-error" hidden></span>
                </label>

                <p class="muted review-form__note">{{ __('Reviews appear after a quick check. We publish honest reviews, good and bad, and only remove spam and rudeness.') }}</p>

                <button type="submit" class="button button--large contact-form__submit" data-contact-submit>
                    <span data-contact-submit-label data-busy-label="{{ __('Sending…') }}">{{ __('Send review') }}</span>
                </button>
            </form>
        </div>
    </details>
</section>
