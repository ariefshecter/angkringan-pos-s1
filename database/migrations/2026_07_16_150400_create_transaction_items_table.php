<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transaksi')->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained('menu')->nullOnDelete();
            $table->string('menu_name');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();

            $table->index(['transaction_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_transaksi');
    }
};
