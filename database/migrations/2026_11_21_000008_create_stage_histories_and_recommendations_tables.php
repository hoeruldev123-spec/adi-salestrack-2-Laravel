<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opportunity_stage_histories')) {
            Schema::create('opportunity_stage_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
                $table->string('previous_stage', 100)->nullable();
                $table->string('current_stage', 100);
                $table->decimal('previous_probability', 5, 2)->nullable();
                $table->decimal('current_probability', 5, 2);
                $table->dateTime('changed_at');
                $table->timestamps();

                $table->index(['opportunity_id', 'changed_at']);
                $table->index('current_stage');
            });
        }

        if (Schema::hasTable('forecast_recommendations')) {
            Schema::dropIfExists('forecast_recommendations');
        }

        Schema::create('forecast_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recommendation_type', 100);
            $table->enum('priority', ['HIGH', 'MEDIUM', 'LOW'])->default('MEDIUM');
            $table->string('recommendation_title', 200);
            $table->text('recommendation_description');
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->decimal('probability', 5, 2)->nullable();
            $table->text('action_suggestion')->nullable();
            $table->timestamps();

            $table->index(['forecast_run_id', 'priority'], 'frec_run_prio_idx');
            $table->index(['opportunity_id', 'recommendation_type'], 'frec_opp_type_idx');
        });

        Schema::table('forecast_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('forecast_runs', 'historical_start_date')) {
                $table->date('historical_start_date')->nullable()->after('forecast_name');
            }
            if (! Schema::hasColumn('forecast_runs', 'historical_end_date')) {
                $table->date('historical_end_date')->nullable()->after('historical_start_date');
            }
            if (! Schema::hasColumn('forecast_runs', 'forecast_start_date')) {
                $table->date('forecast_start_date')->nullable()->after('historical_end_date');
            }
            if (! Schema::hasColumn('forecast_runs', 'forecast_end_date')) {
                $table->date('forecast_end_date')->nullable()->after('forecast_start_date');
            }
            if (! Schema::hasColumn('forecast_runs', 'revenue_category')) {
                $table->string('revenue_category', 50)->nullable()->default('ALL')->after('forecast_end_date');
            }
            if (! Schema::hasColumn('forecast_runs', 'principal_id')) {
                $table->foreignId('principal_id')->nullable()->after('revenue_category')->constrained('principals')->nullOnDelete();
            }
            if (! Schema::hasColumn('forecast_runs', 'forecast_scope')) {
                $table->string('forecast_scope', 50)->nullable()->default('OPPORTUNITY')->after('principal_id');
            }
        });

        // Sync date values if old columns exist
        DB::table('forecast_runs')->update([
            'forecast_start_date' => DB::raw('COALESCE(forecast_start_date, start_period, period_start)'),
            'forecast_end_date' => DB::raw('COALESCE(forecast_end_date, end_period, period_end)'),
        ]);

        Schema::table('forecast_results', function (Blueprint $table) {
            if (! Schema::hasColumn('forecast_results', 'random_number')) {
                $table->decimal('random_number', 10, 8)->nullable()->after('simulation_iteration');
            }
            if (! Schema::hasColumn('forecast_results', 'is_success')) {
                $table->boolean('is_success')->nullable()->after('simulated_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('forecast_results', function (Blueprint $table) {
            if (Schema::hasColumn('forecast_results', 'random_number')) {
                $table->dropColumn(['random_number', 'is_success']);
            }
        });

        Schema::table('forecast_runs', function (Blueprint $table) {
            if (Schema::hasColumn('forecast_runs', 'principal_id')) {
                $table->dropForeign(['principal_id']);
            }
            $colsToDrop = array_filter([
                'historical_start_date', 'historical_end_date', 'forecast_start_date',
                'forecast_end_date', 'revenue_category', 'principal_id', 'forecast_scope',
            ], fn ($col) => Schema::hasColumn('forecast_runs', $col));

            if (! empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });

        Schema::dropIfExists('forecast_recommendations');
        Schema::dropIfExists('opportunity_stage_histories');
    }
};
