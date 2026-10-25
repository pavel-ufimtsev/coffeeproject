<?php

namespace Tests\Feature\Admin\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.profile.update'), [
                'name' => 'Test Admin',
                'email' => 'admin@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.profile.edit'));

        $admin->refresh();

        $this->assertSame('Test Admin', $admin->name);
        $this->assertSame('admin@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.profile.update'), [
                'name' => 'Test Admin',
                'email' => $admin->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.profile.edit'));

        $this->assertNotNull($admin->refresh()->email_verified_at);
    }
}
