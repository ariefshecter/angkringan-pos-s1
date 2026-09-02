<?php

namespace Tests;

use App\Models\Device;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function activateDevice(string $type = Device::TYPE_KIOSK): static
    {
        $token = Str::random(80);
        Device::query()->create([
            'name' => $type === Device::TYPE_ADMIN ? 'Test Admin Device' : 'Test Kiosk Device',
            'type' => $type,
            'activation_code_hash' => hash('sha256', Str::random()),
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        return $this->withCookie('device_token', $token);
    }
}
