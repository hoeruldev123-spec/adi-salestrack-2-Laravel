<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('forecast_type')->default('OPPORTUNITY');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('simulation_count')->default(10000);
            $table->unsignedTinyInteger('confidence_level')->default(90);
            $table->decimal('target_revenue', 15, 2)->nullable();
            $table->string('status')->default('PENDING');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['forecast_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_runs');
    }
};
