<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::cases() as $role) {
            Role::firstOrCreate([
                'name' => $role->value,
            ], [
                'description' => $role->label(),
            ]);
        }

        $adminRole = Role::where('name', RoleEnum::ADMIN->value)->first();
        $salesRole = Role::where('name', RoleEnum::SALES->value)->first();
        $managerRole = Role::where('name', RoleEnum::SALES_MANAGER->value)->first();
        $managementRole = Role::where('name', RoleEnum::MANAGEMENT->value)->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@salestrack.app'],
            [
                'name' => 'System Administrator',
                'password' => 'password',
                'role_id' => $adminRole->id,
                'status' => UserStatus::ACTIVE->value,
            ]
        );

        $salesUsers = User::factory()->count(6)->create(['role_id' => $salesRole->id]);
        $managerUser = User::factory()->create([
            'name' => 'Sales Manager',
            'email' => 'manager@salestrack.app',
            'role_id' => $managerRole->id,
        ]);
        $managementUser = User::factory()->create([
            'name' => 'Management',
            'email' => 'management@salestrack.app',
            'role_id' => $managementRole->id,
        ]);

        $companies = Company::factory()->count(15)->create();
        $principals = MasterPrincipal::factory()->count(8)->create();

        $products = [];
        foreach ($principals as $principal) {
            foreach (['PRODUCT', 'PRODUCT', 'SERVICE'] as $type) {
                $products[] = Product::factory()->create([
                    'principal_id' => $principal->id,
                    'type' => $type,
                ]);
            }
        }

        $this->seedSalesHistory($companies, $salesUsers, $products);
        $this->seedOpportunities($companies, $salesUsers, $principals, $products);

        $this->command?->info('Seeded: 4 roles, '.(4 + $salesUsers->count()).' users, '
            .$companies->count().' companies, '.$principals->count().' principals, '
            .count($products).' products, sales history & opportunities.');
    }

    private function seedSalesHistory($companies, $salesUsers, $products): void
    {
        $now = now();
        for ($i = 0; $i < 22; $i++) {
            $month = $now->copy()->subMonths($i);

            foreach ($salesUsers->take(4) as $user) {
                $transactionCount = random_int(2, 5);
                for ($t = 0; $t < $transactionCount; $t++) {
                    $company = $companies->random();
                    $transaction = SalesTransaction::create([
                        'company_id' => $company->id,
                        'user_id' => $user->id,
                        'transaction_date' => $month->copy()->subDays(random_int(0, 25))->format('Y-m-d'),
                        'transaction_number' => 'STX-'.$month->format('Ym').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
                        'total_amount' => 0,
                        'status' => 'PAID',
                    ]);

                    $total = 0;
                    $itemCount = random_int(1, 4);
                    foreach (collect($products)->random($itemCount) as $product) {
                        $quantity = random_int(1, 10);
                        $subtotal = $quantity * $product->price;
                        $total += $subtotal;

                        SalesTransactionItem::create([
                            'sales_transaction_id' => $transaction->id,
                            'product_id' => $product->id,
                            'quantity' => $quantity,
                            'unit_price' => $product->price,
                            'subtotal' => $subtotal,
                        ]);
                    }

                    $transaction->update(['total_amount' => $total]);
                }
            }
        }
    }

    private function seedOpportunities($companies, $salesUsers, $principals, $products): void
    {
        foreach ($salesUsers as $user) {
            $opportunityCount = random_int(5, 10);
            for ($o = 0; $o < $opportunityCount; $o++) {
                $roll = random_int(1, 100);
                $createdAt = now()->subDays(random_int(5, 120));
                $opportunity = Opportunity::factory()
                    ->when($roll <= 20, fn ($f) => $f->won())
                    ->when($roll > 20 && $roll <= 35, fn ($f) => $f->lost())
                    ->create([
                        'company_id' => $companies->random()->id,
                        'user_id' => $user->id,
                        'principal_id' => $principals->random()->id,
                        'created_at' => $createdAt,
                        'updated_at' => $roll <= 35
                            ? $createdAt->copy()->addDays(random_int(5, 60))
                            : $createdAt,
                    ]);

                foreach (collect($products)->random(random_int(1, 3)) as $product) {
                    $quantity = random_int(1, 10);
                    OpportunityItem::create([
                        'opportunity_id' => $opportunity->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'discount' => random_int(0, 3) === 0 ? $product->price * $quantity * 0.05 : 0,
                        'subtotal' => $quantity * $product->price,
                    ]);
                }

                Activity::factory()->count(random_int(1, 4))->create([
                    'opportunity_id' => $opportunity->id,
                    'user_id' => $user->id,
                ]);
            }
        }
    }
}
