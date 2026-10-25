<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_access_the_admin_area()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_unverified_customers_are_forbidden_rather_than_asked_to_verify()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admins_can_visit_the_dashboard()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('stats.users', 2)
                ->where('stats.admins', 1),
            );
    }

    public function test_admins_must_set_up_two_factor_when_required()
    {
        config(['app.admin.require_two_factor' => true]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.security.edit'));

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk();
    }

    public function test_admins_with_two_factor_can_use_the_admin_area_when_required()
    {
        config(['app.admin.require_two_factor' => true]);

        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_inertia_visits_to_blade_pages_reload_the_full_page()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('home'), ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));
    }
}
