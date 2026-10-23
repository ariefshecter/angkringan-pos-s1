<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Throwable;

class MidtransSettingController extends Controller
{
    /**
     * Display the Midtrans settings form.
     */
    public function edit(): View
    {
        $enabledPayments = config('services.midtrans.enabled_payments', []);
        if (is_string($enabledPayments)) {
            $enabledPayments = explode(',', $enabledPayments);
        }

        $availablePayments = [
            'other_qris' => 'QRIS (GoPay, ShopeePay, OVO, Dana, LinkAja)',
            'bca_va' => 'BCA Virtual Account',
            'bni_va' => 'BNI Virtual Account',
            'bri_va' => 'BRI Virtual Account',
            'cimb_va' => 'CIMB Niaga Virtual Account',
            'permata_va' => 'Permata Virtual Account',
        ];

        $settings = [
            'merchant_id' => Setting::get('midtrans_merchant_id', config('services.midtrans.merchant_id')),
            'client_key' => Setting::get('midtrans_client_key', config('services.midtrans.client_key')),
            'server_key' => Setting::get('midtrans_server_key', config('services.midtrans.server_key')),
            'is_production' => (bool) Setting::get('midtrans_is_production', config('services.midtrans.is_production')),
            'enabled_payments' => $enabledPayments,
        ];

        return view('admin.settings.midtrans', compact('settings', 'availablePayments'));
    }

    /**
     * Update the Midtrans settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'client_key' => ['nullable', 'string', 'max:255'],
            'server_key' => ['nullable', 'string', 'max:255'],
            'is_production' => ['required', 'boolean'],
            'enabled_payments' => ['nullable', 'array'],
            'enabled_payments.*' => ['string', 'max:50'],
        ]);

        $enabledPayments = $request->input('enabled_payments', []);
        $enabledPaymentsString = implode(',', array_filter(array_map('trim', $enabledPayments)));

        Setting::setMany([
            'midtrans_merchant_id' => $validated['merchant_id'] ?? null,
            'midtrans_client_key' => $validated['client_key'] ?? null,
            'midtrans_server_key' => $validated['server_key'] ?? null,
            'midtrans_is_production' => $request->boolean('is_production') ? '1' : '0',
            'midtrans_enabled_payments' => $enabledPaymentsString,
        ]);

        Cache::forget('app_settings_midtrans');

        config([
            'services.midtrans.merchant_id' => $validated['merchant_id'] ?? null,
            'services.midtrans.client_key' => $validated['client_key'] ?? null,
            'services.midtrans.server_key' => $validated['server_key'] ?? null,
            'services.midtrans.is_production' => $request->boolean('is_production'),
            'services.midtrans.enabled_payments' => $enabledPayments,
        ]);

        return redirect()->route('admin.settings.midtrans.edit')
            ->with('status', 'Pengaturan Midtrans berhasil diperbarui.');
    }

    /**
     * Test connection and authentication to Midtrans API without creating an order.
     */
    public function testConnection(Request $request): JsonResponse|RedirectResponse
    {
        $isProduction = $request->has('is_production')
            ? $request->boolean('is_production')
            : (bool) Setting::get('midtrans_is_production', config('services.midtrans.is_production'));

        $serverKey = $request->has('server_key')
            ? trim((string) $request->input('server_key'))
            : (string) Setting::get('midtrans_server_key', config('services.midtrans.server_key'));

        if ($serverKey === '') {
            $result = [
                'connected' => false,
                'status_code' => 400,
                'environment' => $isProduction ? 'Production' : 'Sandbox',
                'message' => 'Server Key masih kosong. Masukkan Server Key terlebih dahulu.',
                'warning' => null,
            ];

            return $request->expectsJson()
                ? response()->json($result, 422)
                : redirect()->back()->with('test_result', $result);
        }

        $warning = null;
        if ($isProduction && str_starts_with($serverKey, 'SB-')) {
            $warning = 'Perhatian: Mode Production dipilih, tetapi Server Key Anda diawali prefix "SB-" (format Sandbox).';
        } elseif (! $isProduction && ! str_starts_with($serverKey, 'SB-')) {
            $warning = 'Perhatian: Mode Sandbox dipilih, tetapi Server Key Anda tidak diawali prefix "SB-".';
        }

        $baseUrl = $isProduction ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';

        try {
            $response = Http::timeout(8)
                ->withBasicAuth($serverKey, '')
                ->get($baseUrl . '/v2/probe-connection-check/status');

            $status = $response->status();

            if ($status === 401) {
                $result = [
                    'connected' => false,
                    'status_code' => 401,
                    'environment' => $isProduction ? 'Production' : 'Sandbox',
                    'message' => 'Koneksi gagal: Server Key tidak valid atau ditolak oleh Midtrans (HTTP 401 Unauthorized). Periksa Server Key Anda.',
                    'warning' => $warning,
                ];
            } elseif ($status === 404 || (string) $response->json('status_code') === '404') {
                $result = [
                    'connected' => true,
                    'status_code' => 200,
                    'environment' => $isProduction ? 'Production' : 'Sandbox',
                    'message' => 'Koneksi Berhasil! Server Key valid dan terautentikasi oleh server Midtrans (' . ($isProduction ? 'Production' : 'Sandbox') . ').',
                    'warning' => $warning,
                ];
            } else {
                $result = [
                    'connected' => false,
                    'status_code' => $status,
                    'environment' => $isProduction ? 'Production' : 'Sandbox',
                    'message' => 'Respons dari Midtrans (HTTP ' . $status . '): ' . ($response->json('status_message') ?? $response->body()),
                    'warning' => $warning,
                ];
            }
        } catch (Throwable $e) {
            $result = [
                'connected' => false,
                'status_code' => 500,
                'environment' => $isProduction ? 'Production' : 'Sandbox',
                'message' => 'Gagal menghubungi server Midtrans: ' . $e->getMessage(),
                'warning' => $warning,
            ];
        }

        return $request->expectsJson()
            ? response()->json($result)
            : redirect()->back()->with('test_result', $result);
    }
}
