<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activities') && ! Schema::hasTable('sales_activities')) {
            Schema::rename('activities', 'sales_activities');
        }

        if (Schema::hasTable('sales_activities') && ! Schema::hasColumn('sales_activities', 'activity_type')) {
            Schema::table('sales_activities', function (Blueprint $table) {
                $table->string('activity_type', 100)->nullable()->after('user_id');
            });

            DB::table('sales_activities')->update([
                'activity_type' => DB::raw('type'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_activities') && ! Schema::hasTable('activities')) {
            if (Schema::hasColumn('sales_activities', 'activity_type')) {
                Schema::table('sales_activities', function (Blueprint $table) {
                    $table->dropColumn('activity_type');
                });
            }

            Schema::rename('sales_activities', 'activities');
        }
    }
};
