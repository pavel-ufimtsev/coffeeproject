<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the initial admin account from the ADMIN_* environment values.
     *
     * An existing account keeps its name and password, so re-running this
     * seeder does not reset a password that was changed through the UI. It
     * is only given the admin role if it does not have it yet.
     */
    public function run(): void
    {
        $name = config('app.admin.name');
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        if (blank($email) || blank($password)) {
            $this->command->warn('Skipping admin account: ADMIN_EMAIL and ADMIN_PASSWORD are not set.');

            return;
        }

        $user = User::firstOrNew(['email' => $email]);

        if ($user->exists) {
            if (! $user->isAdmin()) {
                $user->forceFill(['role' => Role::Admin])->save();
                $this->command->info("Existing account {$email} promoted to admin.");

                return;
            }

            $this->command->info("Admin account {$email} already exists, left unchanged.");

            return;
        }

        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'role' => Role::Admin,
            'email_verified_at' => now(),
        ])->save();

        $this->command->info("Admin account {$email} created.");
    }
}
