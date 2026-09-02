<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table): void {
            $table->json('midtrans_order_id_history')->nullable()->after('midtrans_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table): void {
            $table->dropColumn('midtrans_order_id_history');
        });
    }
};
