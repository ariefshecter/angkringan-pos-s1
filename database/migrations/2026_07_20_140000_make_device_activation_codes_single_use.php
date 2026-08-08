<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('activation_code_hash')->nullable()->change();
            $table->timestamp('activated_at')->nullable()->after('is_active');
        });

        DB::table('devices')
            ->whereNotNull('token_hash')
            ->update([
                'activation_code_hash' => null,
                'activated_at' => DB::raw('COALESCE(last_seen_at, CURRENT_TIMESTAMP)'),
            ]);

        Schema::table('devices', function (Blueprint $table) {
            $table->unique('activation_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['activation_code_hash']);
            $table->dropColumn('activated_at');
        });

        DB::table('devices')
            ->whereNull('activation_code_hash')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn (object $device) => DB::table('devices')->where('id', $device->id)->update([
                'activation_code_hash' => hash('sha256', 'ROLLBACK-'.$device->id),
            ]));

        Schema::table('devices', function (Blueprint $table) {
            $table->string('activation_code_hash')->nullable(false)->change();
        });
    }
};
