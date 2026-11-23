<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            if (! Schema::hasColumn('opportunities', 'project_name')) {
                $table->string('project_name', 200)->nullable()->after('title');
            }
            if (! Schema::hasColumn('opportunities', 'client_name')) {
                $table->string('client_name', 200)->nullable()->after('project_name');
            }
            if (! Schema::hasColumn('opportunities', 'sa_pic')) {
                $table->string('sa_pic', 150)->nullable()->after('principal_id');
            }
            if (! Schema::hasColumn('opportunities', 'license_type')) {
                $table->string('license_type', 50)->nullable()->after('sa_pic');
            }
            if (! Schema::hasColumn('opportunities', 'progress')) {
                $table->string('progress', 100)->nullable()->after('license_type');
            }
            if (! Schema::hasColumn('opportunities', 'deal_registration')) {
                $table->string('deal_registration', 100)->nullable()->after('probability');
            }
            if (! Schema::hasColumn('opportunities', 'estimated_value')) {
                $table->decimal('estimated_value', 18, 2)->nullable()->after('value');
            }
            if (! Schema::hasColumn('opportunities', 'closing_date')) {
                $table->date('closing_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('opportunities', 'actual_closing_date')) {
                $table->date('actual_closing_date')->nullable()->after('closing_date');
            }
            if (! Schema::hasColumn('opportunities', 'remarks')) {
                $table->text('remarks')->nullable()->after('source');
            }
        });

        $updates = [
            'project_name' => DB::raw('COALESCE(project_name, title)'),
            'estimated_value' => DB::raw('COALESCE(estimated_value, value)'),
            'client_name' => DB::raw('COALESCE(client_name, (SELECT name FROM companies WHERE companies.id = opportunities.company_id))'),
            'progress' => DB::raw('COALESCE(progress, stage)'),
            'closing_date' => DB::raw('COALESCE(closing_date, expected_close_date)'),
        ];

        if (Schema::hasColumn('opportunities', 'actual_close_date')) {
            $updates['actual_closing_date'] = DB::raw('COALESCE(actual_closing_date, actual_close_date)');
        }

        DB::table('opportunities')->update($updates);

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('principal_id')->nullable()->change();
        });

        if (! Schema::hasColumn('opportunity_items', 'price_list_id')) {
            Schema::table('opportunity_items', function (Blueprint $table) {
                $table->foreignId('price_list_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('price_lists')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('opportunity_items', 'item_description')) {
            Schema::table('opportunity_items', function (Blueprint $table) {
                $table->string('item_description', 255)->nullable()->after('price_list_id');
            });
        }

        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->default(1)->change();
        });

        DB::table('opportunity_items')->update([
            'item_description' => DB::raw('COALESCE(item_description, (SELECT name FROM products WHERE products.id = opportunity_items.product_id))'),
        ]);
    }

    public function down(): void
    {
        Schema::table('opportunity_items', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn(['price_list_id', 'item_description']);
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn([
                'project_name', 'client_name', 'sa_pic', 'license_type', 'progress',
                'deal_registration', 'estimated_value', 'actual_closing_date', 'remarks',
            ]);
        });
    }
};
