<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD');

        if (app()->environment('production') && ! $adminPassword) {
            throw new RuntimeException('Set ADMIN_PASSWORD before seeding the production database.');
        }

        foreach (['admin', 'manager', 'cashier', 'stock_keeper'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            ['name' => 'Admin', 'password' => Hash::make($adminPassword ?: 'password'), 'is_active' => true]
        );
        $admin->roles()->syncWithoutDetaching(Role::where('name', 'admin')->pluck('id')->all());

        Unit::firstOrCreate(['name' => 'Piece'], ['symbol' => 'pcs']);
        Unit::firstOrCreate(['name' => 'Box'], ['symbol' => 'box']);
        Unit::firstOrCreate(['name' => 'Kilogram'], ['symbol' => 'kg']);
        Warehouse::firstOrCreate(['name' => 'Main Warehouse'], ['location' => 'Phnom Penh']);
    }
}
