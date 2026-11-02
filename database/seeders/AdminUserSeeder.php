<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'manager', 'sales'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        $users = [
            ['Admin',   'admin@example.com',   'admin'],
            ['Manager', 'manager@example.com', 'manager'],
            ['Cashier', 'sales@example.com',   'sales'],
        ];

        foreach ($users as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password123'), 'is_active' => true]
            );
            $user->syncRoles([$role]);
        }
    }
}