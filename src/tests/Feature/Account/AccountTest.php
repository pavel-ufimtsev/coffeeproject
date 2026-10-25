<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('account.index'))->assertRedirect(route('login'));
    }

    public function test_account_pages_are_displayed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.index'))->assertOk()->assertViewIs('account.index');
        $this->actingAs($user)->get(route('account.settings'))->assertOk()->assertViewIs('account.settings');
    }

    public function test_unverified_users_can_use_their_account()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertOk()
            ->assertSee(__('Please confirm your email address. We sent you a link when you signed up.'));
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.settings'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_errors_use_their_own_error_bag()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.settings'))
            ->patch(route('account.profile.update'), ['name' => '', 'email' => $user->email])
            ->assertSessionHasErrorsIn('profile', 'name')
            ->assertRedirect(route('account.settings'));
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.settings'));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.settings'))
            ->put(route('account.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrorsIn('password', 'current_password')
            ->assertRedirect(route('account.settings'));
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.settings'))
            ->delete(route('account.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('deleteAccount', 'password')
            ->assertRedirect(route('account.settings'));

        $this->assertNotNull($user->fresh());
    }

    public function test_admins_cannot_delete_their_own_account()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }
}
