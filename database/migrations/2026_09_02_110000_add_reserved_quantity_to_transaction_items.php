<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table): void {
            $table->unsignedInteger('reserved_quantity')->nullable()->after('quantity');
        });

        DB::table('detail_transaksi')
            ->join('transaksi', 'transaksi.id', '=', 'detail_transaksi.transaction_id')
            ->whereIn('transaksi.payment_status', ['pending', 'replacing', 'cancelling'])
            ->where('transaksi.reservation_active', true)
            ->update(['detail_transaksi.reserved_quantity' => DB::raw('detail_transaksi.quantity')]);

        DB::table('detail_transaksi')
            ->whereNull('reserved_quantity')
            ->update(['reserved_quantity' => 0]);
    }

    public function down(): void
    {
        Schema::table('detail_transaksi', function (Blueprint $table): void {
            $table->dropColumn('reserved_quantity');
        });
    }
};
