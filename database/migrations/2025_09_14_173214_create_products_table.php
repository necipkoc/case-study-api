<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->index(['category_id']);
            $table->index(['price']);
            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
