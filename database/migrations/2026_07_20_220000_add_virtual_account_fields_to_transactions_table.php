<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('midtrans_bank', 30)->nullable()->after('midtrans_payment_type');
            $table->string('midtrans_va_number', 50)->nullable()->after('midtrans_bank');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['midtrans_bank', 'midtrans_va_number']);
        });
    }
};
