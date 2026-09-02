<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('kategori')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reserved_stock')->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();

            $table->index(['category_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu');
    }
};
