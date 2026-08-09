<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first Society administrator for local development.
 *
 * The password is only ever the default in a local environment; anywhere else
 * it must be supplied through ADMIN_PASSWORD.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if ($password === null && ! app()->environment(['local', 'testing'])) {
            $this->command?->warn('ADMIN_PASSWORD not set — skipping admin user.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@kslive.au')],
            [
                'name' => 'Society Administrator',
                'password' => Hash::make($password ?? 'kebab-society'),
                'is_admin' => true,
            ],
        );
    }
}
