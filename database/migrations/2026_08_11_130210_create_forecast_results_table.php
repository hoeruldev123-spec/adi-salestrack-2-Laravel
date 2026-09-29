<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum', 15, 2);
            $table->decimal('maximum', 15, 2);
            $table->decimal('mean', 15, 2);
            $table->decimal('median', 15, 2);
            $table->decimal('p10', 15, 2);
            $table->decimal('p25', 15, 2);
            $table->decimal('p50', 15, 2);
            $table->decimal('p75', 15, 2);
            $table->decimal('p90', 15, 2);
            $table->decimal('p95', 15, 2);
            $table->decimal('target_probability', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_results');
    }
};
