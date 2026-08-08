<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Device;
use App\Models\User;
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
        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@angkringans1.test'),
        ], [
            'name' => env('ADMIN_NAME', 'Administrator'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'role' => 'admin',
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

        collect([
            [
                'name' => 'Device Admin',
                'type' => Device::TYPE_ADMIN,
                'code' => env('ADMIN_DEVICE_CODE', 'ADMIN-S1-2026'),
            ],
            [
                'name' => 'Device Pelanggan',
                'type' => Device::TYPE_KIOSK,
                'code' => env('KIOSK_DEVICE_CODE', 'KIOSK-S1-2026'),
            ],
        ])->each(function (array $device): void {
            Device::query()->firstOrCreate([
                'name' => $device['name'],
            ], [
                'type' => $device['type'],
                'activation_code_hash' => hash('sha256', $device['code']),
                'is_active' => true,
            ]);
        });
    }
}
