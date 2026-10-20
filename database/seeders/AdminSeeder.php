<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            $this->command?->warn('ADMIN_PASSWORD is not set; skipping admin user.');

            return;
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->fill([
            'name' => (string) env('ADMIN_NAME', 'BDLG Admin'),
            'password' => $password,
        ]);
        $admin->role = UserRole::Admin;
        $admin->email_verified_at = now();
        $admin->save();
    }
}
