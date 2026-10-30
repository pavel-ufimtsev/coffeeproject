<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_land_on_their_account_after_login()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.index'));
    }

    public function test_admins_land_on_the_admin_dashboard_after_login()
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_logged_in_users_visiting_login_are_sent_to_their_home()
    {
        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('account.index'));
        $this->actingAs(User::factory()->admin()->create())->get(route('login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_registration_always_creates_a_customer()
    {
        $this->withSession([CreateNewUser::SHOWN_AT => now()->subMinute()->timestamp])->post(route('register.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'role' => 'admin',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame(Role::User, User::where('email', 'sneaky@example.com')->first()->role);
    }
}
