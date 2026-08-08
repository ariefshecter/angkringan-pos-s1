<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->after('handled_by')->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('cancelled_at');
            $table->timestamp('printed_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn(['confirmed_at', 'printed_at']);
        });
    }
};
