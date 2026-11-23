<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('specification_name', 100);
            $table->string('specification_value', 255);
            $table->string('unit', 50)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'specification_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
    }
};
