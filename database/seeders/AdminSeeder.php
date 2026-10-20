<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('localguides.admin.email');
        $password = config('localguides.admin.password');

        if (! $password) {
            $this->command?->warn('ADMIN_PASSWORD is not set; skipping admin user.');

            return;
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => (string) config('localguides.admin.name'),
            'password' => $password,
        ]);
        $admin->role = UserRole::Admin;
        $admin->email_verified_at = now();
        $admin->save();
    }
}
