<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table): void {
            $table->string('midtrans_order_id', 50)->nullable()->unique()->after('order_number');
            $table->boolean('reservation_active')->default(true)->after('order_status');
        });

        DB::table('transaksi')
            ->whereNotIn('payment_status', ['pending', 'replacing', 'cancelling'])
            ->update(['reservation_active' => false]);

        DB::table('transaksi')
            ->where('payment_method', 'midtrans')
            ->whereNull('midtrans_order_id')
            ->update(['midtrans_order_id' => DB::raw('order_number')]);
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table): void {
            $table->dropUnique(['midtrans_order_id']);
            $table->dropColumn(['midtrans_order_id', 'reservation_active']);
        });
    }
};
