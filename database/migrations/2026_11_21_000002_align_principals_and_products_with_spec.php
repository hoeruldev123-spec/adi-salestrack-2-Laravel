<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_principals') && ! Schema::hasTable('principals')) {
            Schema::rename('master_principals', 'principals');
        }

        if (Schema::hasTable('principals') && ! Schema::hasColumn('principals', 'is_active')) {
            Schema::table('principals', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('website');
            });

            DB::table('principals')->update([
                'is_active' => DB::raw("CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END"),
            ]);
        }

        if (! Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('principal_id')
                    ->constrained('product_categories')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('products', 'product_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('product_type', 50)->nullable()->after('name');
                $table->string('unit', 50)->nullable()->after('description');
                $table->boolean('is_active')->default(true)->after('status');
            });

            DB::table('products')->update([
                'product_type' => DB::raw('type'),
                'is_active' => DB::raw("CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END"),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'product_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropColumn(['category_id', 'product_type', 'unit', 'is_active']);
            });
        }

        if (Schema::hasTable('principals') && ! Schema::hasTable('master_principals')) {
            if (Schema::hasColumn('principals', 'is_active')) {
                Schema::table('principals', function (Blueprint $table) {
                    $table->dropColumn('is_active');
                });
            }

            Schema::rename('principals', 'master_principals');
        }
    }
};
