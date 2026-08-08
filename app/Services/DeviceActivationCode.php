<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Str;

class DeviceActivationCode
{
    public function generate(string $type): string
    {
        $prefix = $type === Device::TYPE_ADMIN ? 'ADMIN' : 'KIOSK';

        do {
            $code = $prefix.'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
        } while (Device::query()->where('activation_code_hash', $this->hash($code))->exists());

        return $code;
    }

    public function hash(string $code): string
    {
        return hash('sha256', Str::upper(trim($code)));
    }
}
