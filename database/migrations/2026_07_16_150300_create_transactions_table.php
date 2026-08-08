<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('table_number', 30)->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('total');
            $table->string('payment_method', 30);
            $table->string('payment_status', 30)->default('pending')->index();
            $table->string('order_status', 30)->default('new')->index();
            $table->string('midtrans_transaction_id')->nullable()->unique();
            $table->string('midtrans_payment_type', 50)->nullable();
            $table->text('midtrans_snap_token')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
