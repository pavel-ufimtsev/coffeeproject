<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_is_russian_by_default()
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSee('Войти');
    }

    public function test_lang_parameter_stores_the_choice_and_redirects_to_the_clean_url()
    {
        $this->get('/login?lang=en&foo=bar')
            ->assertRedirect(url('/login?foo=bar'))
            ->assertCookie(SetLocale::COOKIE, 'en');
    }

    public function test_the_stored_choice_switches_the_interface_to_english()
    {
        $this->withCookie(SetLocale::COOKIE, 'en')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Log in');
    }

    public function test_unsupported_languages_are_ignored()
    {
        $this->get('/login?lang=de')
            ->assertRedirect(url('/login'))
            ->assertCookieMissing(SetLocale::COOKIE);

        $this->withCookie(SetLocale::COOKIE, 'de')
            ->get(route('login'))
            ->assertSee('<html lang="ru">', false);
    }

    public function test_inertia_visits_reload_the_page_when_switching_language()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin?lang=en', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/admin'));
    }

    public function test_the_admin_receives_russian_translations()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ru')
                ->where('translations.Users', 'Пользователи'),
            );
    }

    public function test_the_admin_receives_no_translations_in_english()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withCookie(SetLocale::COOKIE, 'en')
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations', []),
            );
    }
}
