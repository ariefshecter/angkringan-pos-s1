<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceActivationController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MidtransSettingController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\StockController;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $token = $request->cookie('device_token');
    $device = $token ? Device::query()->where('token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;

    return $device?->type === Device::TYPE_KIOSK
        ? redirect()->route('kiosk.index')
        : redirect()->route('login');
});

Route::get('/device/activate', [DeviceActivationController::class, 'create'])->name('device.activate');
Route::post('/device/activate', [DeviceActivationController::class, 'store'])->name('device.activate.store');
Route::post('/midtrans/notification', MidtransWebhookController::class)->name('midtrans.notification');

Route::middleware('device:kiosk')->prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', KioskController::class)->name('index');
    Route::get('/stocks', [KioskController::class, 'stocks'])->name('stocks');
    Route::get('/catalog', [KioskController::class, 'catalog'])->name('catalog');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
    Route::patch('/cart/{menu}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{menu}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders/{orderNumber}', [CheckoutController::class, 'show'])->name('orders.show');
    Route::get('/orders/{orderNumber}/status', [CheckoutController::class, 'status'])->name('orders.status');
    Route::post('/orders/{orderNumber}/change-payment', [CheckoutController::class, 'changePayment'])->name('orders.change-payment');
    Route::post('/orders/{orderNumber}/cancel-order', [CheckoutController::class, 'cancelOrder'])->name('orders.cancel-order');
});

Route::middleware('staff')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/admin/stocks', [StockController::class, 'index'])->name('admin.stocks.index');
    Route::get('/admin/stocks/feed', [StockController::class, 'feed'])->name('admin.stocks.feed');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('orders/feed', [OrderController::class, 'feed'])->name('orders.feed');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{transaction}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{transaction}/confirm-cash', [OrderController::class, 'confirmCash'])->name('orders.confirm-cash');
        Route::post('orders/{transaction}/accept-digital', [OrderController::class, 'acceptDigital'])->name('orders.accept-digital');
        Route::post('orders/{transaction}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::get('orders/{transaction}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');
        Route::get('orders/{transaction}/receipt.pdf', [OrderController::class, 'receiptPdf'])->name('orders.receipt.pdf');
        Route::post('orders/{transaction}/printed', [OrderController::class, 'markPrinted'])->name('orders.printed');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('reports/sales', [SalesReportController::class, 'index'])->name('reports.sales');
        Route::get('reports/sales.pdf', [SalesReportController::class, 'pdf'])->name('reports.pdf');
        Route::post('reports/sales', [SalesReportController::class, 'store'])->name('reports.store');
        Route::get('reports/snapshots', [SalesReportController::class, 'snapshots'])->name('reports.snapshots');
        Route::get('reports/snapshots/{report}', [SalesReportController::class, 'show'])->name('reports.show');
        Route::get('reports/snapshots/{report}/pdf', [SalesReportController::class, 'snapshotPdf'])->name('reports.snapshot.pdf');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('menus', MenuController::class)->except('show');
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('devices/create', [DeviceController::class, 'create'])->name('devices.create');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::post('devices/{device}/regenerate', [DeviceController::class, 'regenerate'])->name('devices.regenerate');
        Route::patch('devices/{device}/toggle', [DeviceController::class, 'toggle'])->name('devices.toggle');
        Route::resource('kasir', KasirController::class)->except(['show', 'destroy']);
        Route::patch('kasir/{kasir}/toggle', [KasirController::class, 'toggle'])->name('kasir.toggle');
        Route::patch('kasir/{kasir}/reset-password', [KasirController::class, 'resetPassword'])->name('kasir.reset-password');
        Route::get('settings/midtrans', [MidtransSettingController::class, 'edit'])->name('settings.midtrans.edit');
        Route::put('settings/midtrans', [MidtransSettingController::class, 'update'])->name('settings.midtrans.update');
        Route::post('settings/midtrans/test', [MidtransSettingController::class, 'testConnection'])->name('settings.midtrans.test');
    });
});

require __DIR__.'/auth.php';
