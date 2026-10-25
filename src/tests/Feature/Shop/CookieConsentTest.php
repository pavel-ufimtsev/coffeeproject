<?php

namespace Tests\Feature\Shop;

use App\Support\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_banner_shows_until_the_visitor_answers()
    {
        $this->get('/')->assertOk()->assertSee('data-cookie-banner', false)->assertSee('Только необходимые');

        $this->withCookie(CookieConsent::COOKIE, CookieConsent::NECESSARY)
            ->get('/')
            ->assertOk()
            ->assertDontSee('data-cookie-banner', false);
    }

    public function test_an_answer_is_remembered_for_a_year()
    {
        $response = $this->postJson(route('cookie-consent'), ['choice' => CookieConsent::ALL])
            ->assertNoContent()
            ->assertCookie(CookieConsent::COOKIE, CookieConsent::ALL);

        $cookie = $response->getCookie(CookieConsent::COOKIE);
        $this->assertEqualsWithDelta(now()->addDays(CookieConsent::LIFETIME_DAYS)->timestamp, $cookie->getExpiresTime(), 60);
    }

    public function test_without_javascript_the_form_returns_to_the_page()
    {
        $this->from('/coffee')
            ->post(route('cookie-consent'), ['choice' => CookieConsent::NECESSARY])
            ->assertRedirect('/coffee')
            ->assertCookie(CookieConsent::COOKIE, CookieConsent::NECESSARY);
    }

    public function test_only_known_answers_are_accepted()
    {
        $this->postJson(route('cookie-consent'), ['choice' => 'maybe'])->assertJsonValidationErrors('choice');
    }
}
