<?php

namespace Tests\Feature;

use App\Enums\OpportunityStage;
use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Opportunity;
use App\Models\OpportunityLog;
use App\Models\Product;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = Role::ADMIN->value): User
    {
        $roleModel = RoleModel::firstOrCreate(['name' => $role], ['description' => $role]);

        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    private function payload(User $user, ?Product $product = null): array
    {
        $company = Company::factory()->create();
        $principal = MasterPrincipal::factory()->create();
        $product ??= Product::factory()->create(['principal_id' => $principal->id]);

        return [
            'company_id' => $company->id,
            'principal_id' => $principal->id,
            'title' => 'Data Warehouse Project',
            'stage' => 'PROPOSAL',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ];
    }

    public function test_sales_can_create_opportunity_with_items(): void
    {
        $sales = $this->user(Role::SALES->value);
        $payload = $this->payload($sales);
        $product = Product::first();
        $expectedValue = $product->price * 2;

        $this->actingAs($sales, 'sanctum')
            ->postJson('/api/v1/opportunities', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', 'Data Warehouse Project')
            ->assertJsonPath('data.value', (int) $expectedValue)
            ->assertJsonPath('data.probability', 50);

        $this->assertDatabaseHas('opportunity_logs', [
            'description' => 'Opportunity created',
        ]);
    }

    public function test_default_probability_follows_stage(): void
    {
        $sales = $this->user(Role::SALES->value);
        $payload = $this->payload($sales);
        $payload['stage'] = 'NEGOTIATION';

        $this->actingAs($sales, 'sanctum')
            ->postJson('/api/v1/opportunities', $payload)
            ->assertCreated()
            ->assertJsonPath('data.probability', 70);
    }

    public function test_stage_change_auto_updates_probability(): void
    {
        $sales = $this->user(Role::SALES->value);
        $opportunity = Opportunity::factory()->create([
            'user_id' => $sales->id,
            'stage' => OpportunityStage::LEAD->value,
            'probability' => 10,
        ]);

        $this->actingAs($sales, 'sanctum')
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['stage' => 'CLOSING'])
            ->assertOk()
            ->assertJsonPath('data.probability', 90);
    }

    public function test_won_status_sets_probability_to_100(): void
    {
        $sales = $this->user(Role::SALES->value);
        $opportunity = Opportunity::factory()->create([
            'user_id' => $sales->id,
        ]);

        $this->actingAs($sales, 'sanctum')
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['status' => 'WON'])
            ->assertOk()
            ->assertJsonPath('data.probability', 100);
    }

    public function test_opportunity_change_is_logged(): void
    {
        $sales = $this->user(Role::SALES->value);
        $opportunity = Opportunity::factory()->create([
            'user_id' => $sales->id,
            'title' => 'Old Title',
        ]);

        $this->actingAs($sales, 'sanctum')
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['title' => 'New Title'])
            ->assertOk();

        $this->assertDatabaseHas('opportunity_logs', [
            'opportunity_id' => $opportunity->id,
            'field_name' => 'title',
            'old_value' => 'Old Title',
            'new_value' => 'New Title',
        ]);
    }

    public function test_sales_cannot_update_others_opportunity(): void
    {
        $otherSales = $this->user(Role::SALES->value);
        $opportunity = Opportunity::factory()->create(['user_id' => $otherSales->id]);

        $attacker = $this->user(Role::SALES->value);

        $this->actingAs($attacker, 'sanctum')
            ->putJson("/api/v1/opportunities/{$opportunity->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_manager_can_see_all_opportunities(): void
    {
        $sales = $this->user(Role::SALES->value);
        Opportunity::factory()->count(2)->create(['user_id' => $sales->id]);
        Opportunity::factory()->count(3)->create(['user_id' => $this->user(Role::SALES->value)->id]);

        $manager = $this->user(Role::SALES_MANAGER->value);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_sales_only_sees_own_opportunities(): void
    {
        $sales = $this->user(Role::SALES->value);
        Opportunity::factory()->count(2)->create(['user_id' => $sales->id]);
        Opportunity::factory()->count(3)->create(['user_id' => $this->user(Role::SALES->value)->id]);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/opportunities')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_opportunity_logs_endpoint(): void
    {
        $sales = $this->user(Role::SALES->value);
        $opportunity = Opportunity::factory()->create(['user_id' => $sales->id]);
        OpportunityLog::create([
            'opportunity_id' => $opportunity->id,
            'user_id' => $sales->id,
            'field_name' => 'stage',
            'old_value' => 'LEAD',
            'new_value' => 'PROPOSAL',
        ]);

        $this->actingAs($sales, 'sanctum')
            ->getJson("/api/v1/opportunities/{$opportunity->id}/logs")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
