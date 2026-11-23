<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('pricing_type', 50);
            $table->decimal('unit_price', 18, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('billing_period', 30)->default('ONE_TIME');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'pricing_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
