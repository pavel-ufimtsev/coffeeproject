<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_users_can_be_listed_and_filtered()
    {
        User::factory()->create(['name' => 'Anna Customer']);
        User::factory()->create(['name' => 'Boris Customer']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->has('users.data', 3),
            );

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['search' => 'Anna']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Anna Customer'),
            );

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['role' => 'admin']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $this->admin->id),
            );
    }

    public function test_admins_can_create_users()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Admin',
                'email' => 'new@example.com',
                'role' => 'admin',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new@example.com')->firstOrFail();

        $this->assertSame(Role::Admin, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_admins_can_update_users_and_change_their_role()
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => 'Renamed',
                'email' => $user->email,
                'role' => 'admin',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_admins_cannot_change_their_own_role()
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role' => 'user',
            ])
            ->assertForbidden();

        $this->assertTrue($this->admin->refresh()->isAdmin());
    }

    public function test_admins_can_edit_their_own_details_without_changing_role()
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => 'Still Admin',
                'email' => $this->admin->email,
                'role' => 'admin',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Still Admin', $this->admin->refresh()->name);
    }

    public function test_invalid_roles_are_rejected()
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'superuser',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_admins_can_delete_other_users_but_not_themselves()
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($user->fresh());

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertForbidden();

        $this->assertNotNull($this->admin->fresh());
    }

    public function test_customers_cannot_manage_users()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.edit', $other))->assertForbidden();
        $this->actingAs($user)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'admin',
        ])->assertForbidden();

        $this->assertFalse($user->refresh()->isAdmin());
    }
}
