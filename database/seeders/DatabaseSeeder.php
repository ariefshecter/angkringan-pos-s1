<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Device;
use App\Models\Kasir;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Admin::query()->updateOrCreate([
            'username' => env('ADMIN_USERNAME', 'admin'),
        ], [
            'name' => env('ADMIN_NAME', 'Administrator'),
            'email' => env('ADMIN_EMAIL', 'admin@angkringans1.test'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'is_active' => true,
        ]);

        Kasir::query()->updateOrCreate([
            'username' => env('CASHIER_USERNAME', 'kasir'),
        ], [
            'name' => env('CASHIER_NAME', 'Kasir'),
            'email' => env('CASHIER_EMAIL', 'kasir@angkringans1.test'),
            'password' => Hash::make(env('CASHIER_PASSWORD', 'password')),
            'is_active' => true,
        ]);

        collect(['Makanan', 'Minuman', 'Camilan'])
            ->each(function (string $name, int $index): void {
                Category::query()->updateOrCreate([
                    'slug' => Str::slug($name),
                ], [
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
            });

        Device::query()->firstOrCreate([
            'name' => 'Device Pelanggan',
        ], [
            'type' => Device::TYPE_KIOSK,
            'activation_code_hash' => hash('sha256', env('KIOSK_DEVICE_CODE', 'KIOSK-S1-2026')),
            'is_active' => true,
        ]);
    }
}
