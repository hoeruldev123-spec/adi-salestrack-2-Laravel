<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('simulation_number');
            $table->decimal('total_revenue', 15, 2);
            $table->boolean('target_achieved')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['forecast_run_id', 'simulation_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_simulations');
    }
};
