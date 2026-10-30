<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $this->get(route('register'))->assertOk();
        $this->travel(10)->seconds();

        $response = $this->post(route('register.store'), $this->form());

        $this->assertAuthenticated();
        $response->assertRedirect(route('account.index', absolute: false));
    }

    public function test_forms_posted_without_being_shown_are_rejected()
    {
        $this->post(route('register.store'), $this->form())->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_forms_posted_too_fast_are_rejected()
    {
        $this->get(route('register'));
        $this->travel(1)->seconds();

        $this->post(route('register.store'), $this->form())->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_the_honeypot_rejects_bots()
    {
        $this->withSession([CreateNewUser::SHOWN_AT => now()->subMinute()->timestamp])
            ->post(route('register.store'), $this->form(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_one_ip_can_only_register_a_few_accounts_an_hour()
    {
        foreach (range(1, 6) as $i) {
            auth()->logout();
            $this->withSession([CreateNewUser::SHOWN_AT => now()->subMinute()->timestamp])
                ->post(route('register.store'), $this->form(['email' => "user{$i}@example.com"]));
        }

        $this->assertSame(5, User::count());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }
}
