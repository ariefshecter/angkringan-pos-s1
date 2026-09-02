<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('user_session_id');
            $table->foreign('user_session_id')->references('session_id')->on('user')->restrictOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admin')->nullOnDelete();
            $table->foreignId('kasir_id')->nullable()->constrained('kasir')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('customer_name', 100);
            $table->string('table_number', 30)->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('total');
            $table->string('payment_method', 30);
            $table->string('payment_channel', 30)->nullable()->index();
            $table->string('payment_status', 30)->default('pending')->index();
            $table->string('order_status', 30)->default('new')->index();
            $table->string('midtrans_transaction_id')->nullable()->unique();
            $table->string('midtrans_payment_type', 50)->nullable();
            $table->string('midtrans_bank', 30)->nullable();
            $table->string('midtrans_va_number', 50)->nullable();
            $table->text('midtrans_snap_token')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'payment_status']);
            $table->index(['user_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
