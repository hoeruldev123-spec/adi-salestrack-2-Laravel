<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('forecast_runs', 'warnings')) {
                $table->json('warnings')->nullable()->after('mape');
            }
            if (! Schema::hasColumn('forecast_runs', 'recommendation_thresholds')) {
                $table->json('recommendation_thresholds')->nullable()->after('warnings');
            }
        });

        Schema::table('forecast_results', function (Blueprint $table) {
            $table->index(['forecast_run_id', 'opportunity_id'], 'fres_run_opp_idx');
            $table->index(['forecast_run_id', 'simulation_iteration'], 'fres_run_iteration_idx');
        });
    }

    public function down(): void
    {
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->dropIndex('fres_run_opp_idx');
            $table->dropIndex('fres_run_iteration_idx');
        });

        Schema::table('forecast_runs', function (Blueprint $table) {
            $table->dropColumn(['warnings', 'recommendation_thresholds']);
        });
    }
};
