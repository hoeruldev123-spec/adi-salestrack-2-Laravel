<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('win_rate_adjustment', 5, 2)->default(0);
            $table->decimal('revenue_adjustment', 15, 2)->default(0);
            $table->decimal('opportunity_adjustment', 5, 2)->default(0);
            $table->decimal('target_revenue', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_scenarios');
    }
};
