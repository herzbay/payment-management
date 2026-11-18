<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (empty($name) || empty($email) || empty($password)) {
            throw new RuntimeException(
                'ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD must be configured in the .env file.'
            );
        }

        $role = Role::where('slug', 'super-admin')->firstOrFail();

        $user = User::firstOrNew([
            'email' => $email,
        ]);

        $isNewUser = !$user->exists;

        $user->name = $name;
        $user->role_id = $role->id;

        if ($isNewUser) {
            $user->password = Hash::make($password);
            $user->email_verified_at = now();
        }

        $user->save();
    }
}