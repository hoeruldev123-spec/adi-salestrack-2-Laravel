<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecast_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('forecast_runs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('forecast_runs', 'forecast_name')) {
                $table->string('forecast_name', 150)->nullable()->after('name');
            }
            if (! Schema::hasColumn('forecast_runs', 'start_period')) {
                $table->date('start_period')->nullable()->after('forecast_name');
            }
            if (! Schema::hasColumn('forecast_runs', 'end_period')) {
                $table->date('end_period')->nullable()->after('start_period');
            }
            if (! Schema::hasColumn('forecast_runs', 'predicted_revenue')) {
                $table->decimal('predicted_revenue', 18, 2)->nullable()->after('target_revenue');
            }
            if (! Schema::hasColumn('forecast_runs', 'best_case')) {
                $table->decimal('best_case', 18, 2)->nullable()->after('predicted_revenue');
            }
            if (! Schema::hasColumn('forecast_runs', 'worst_case')) {
                $table->decimal('worst_case', 18, 2)->nullable()->after('best_case');
            }
            if (! Schema::hasColumn('forecast_runs', 'most_likely')) {
                $table->decimal('most_likely', 18, 2)->nullable()->after('worst_case');
            }
            if (! Schema::hasColumn('forecast_runs', 'actual_revenue')) {
                $table->decimal('actual_revenue', 18, 2)->nullable()->after('most_likely');
            }
            if (! Schema::hasColumn('forecast_runs', 'mape')) {
                $table->decimal('mape', 8, 4)->nullable()->after('actual_revenue');
            }
        });

        DB::table('forecast_runs')->update([
            'forecast_name' => DB::raw('COALESCE(forecast_name, name)'),
            'user_id' => DB::raw('COALESCE(user_id, created_by)'),
            'start_period' => DB::raw('COALESCE(start_period, period_start)'),
            'end_period' => DB::raw('COALESCE(end_period, period_end)'),
        ]);

        if (! Schema::hasColumn('forecast_results', 'opportunity_id')) {
            Schema::table('forecast_results', function (Blueprint $table) {
                $table->foreignId('opportunity_id')
                    ->nullable()
                    ->after('forecast_run_id')
                    ->constrained('opportunities')
                    ->nullOnDelete();
            });
        }

        Schema::table('forecast_results', function (Blueprint $table) {
            if (! Schema::hasColumn('forecast_results', 'simulated_value')) {
                $table->decimal('simulated_value', 18, 2)->nullable()->after('opportunity_id');
            }
            if (! Schema::hasColumn('forecast_results', 'probability')) {
                $table->decimal('probability', 5, 2)->nullable()->after('simulated_value');
            }
            if (! Schema::hasColumn('forecast_results', 'simulation_iteration')) {
                $table->unsignedInteger('simulation_iteration')->nullable()->after('probability');
            }
        });

        Schema::table('forecast_results', function (Blueprint $table) {
            $table->decimal('minimum', 15, 2)->nullable()->change();
            $table->decimal('maximum', 15, 2)->nullable()->change();
            $table->decimal('mean', 15, 2)->nullable()->change();
            $table->decimal('median', 15, 2)->nullable()->change();
            $table->decimal('p10', 15, 2)->nullable()->change();
            $table->decimal('p25', 15, 2)->nullable()->change();
            $table->decimal('p50', 15, 2)->nullable()->change();
            $table->decimal('p75', 15, 2)->nullable()->change();
            $table->decimal('p90', 15, 2)->nullable()->change();
            $table->decimal('p95', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('forecast_results', function (Blueprint $table) {
            $table->dropForeign(['opportunity_id']);
            $table->dropColumn(['opportunity_id', 'simulated_value', 'probability', 'simulation_iteration']);
        });

        Schema::table('forecast_runs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id', 'forecast_name', 'predicted_revenue', 'best_case',
                'worst_case', 'most_likely', 'actual_revenue', 'mape',
            ]);
        });
    }
};
