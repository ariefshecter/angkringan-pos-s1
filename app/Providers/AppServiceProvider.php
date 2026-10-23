<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootMidtransConfiguration();
    }

    protected function bootMidtransConfiguration(): void
    {
        try {
            if (Schema::hasTable('settings')) {
                $settings = Cache::remember('app_settings_midtrans', 3600, function () {
                    return Setting::query()
                        ->where('key', 'like', 'midtrans_%')
                        ->pluck('value', 'key')
                        ->all();
                });

                if (! empty($settings)) {
                    if (array_key_exists('midtrans_merchant_id', $settings) && $settings['midtrans_merchant_id'] !== null) {
                        config(['services.midtrans.merchant_id' => $settings['midtrans_merchant_id']]);
                    }
                    if (array_key_exists('midtrans_client_key', $settings) && $settings['midtrans_client_key'] !== null) {
                        config(['services.midtrans.client_key' => $settings['midtrans_client_key']]);
                    }
                    if (array_key_exists('midtrans_server_key', $settings) && $settings['midtrans_server_key'] !== null) {
                        config(['services.midtrans.server_key' => $settings['midtrans_server_key']]);
                    }
                    if (array_key_exists('midtrans_is_production', $settings) && $settings['midtrans_is_production'] !== null) {
                        config(['services.midtrans.is_production' => filter_var($settings['midtrans_is_production'], FILTER_VALIDATE_BOOLEAN)]);
                    }
                    if (array_key_exists('midtrans_enabled_payments', $settings) && filled($settings['midtrans_enabled_payments'])) {
                        $payments = array_values(array_filter(array_map('trim', explode(',', $settings['midtrans_enabled_payments']))));
                        config(['services.midtrans.enabled_payments' => $payments]);
                    }
                }
            }
        } catch (Throwable) {
            // Ignore during early boot or CLI migration
        }
    }
}
