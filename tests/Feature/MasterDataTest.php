<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\MasterPrincipal;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = Role::ADMIN->value): User
    {
        $roleModel = RoleModel::firstOrCreate(['name' => $role], ['description' => $role]);

        return User::factory()->create(['role_id' => $roleModel->id]);
    }

    public function test_admin_can_list_companies(): void
    {
        Company::factory()->count(3)->create();

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_company(): void
    {
        $this->actingAs($this->user(), 'sanctum')
            ->postJson('/api/v1/companies', [
                'name' => 'PT Maju Jaya',
                'industry' => 'Technology',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'PT Maju Jaya');
    }

    public function test_sales_cannot_create_company(): void
    {
        $this->actingAs($this->user(Role::SALES->value), 'sanctum')
            ->postJson('/api/v1/companies', ['name' => 'Test'])
            ->assertForbidden();
    }

    public function test_sales_can_view_companies(): void
    {
        Company::factory()->create();

        $this->actingAs($this->user(Role::SALES->value), 'sanctum')
            ->getJson('/api/v1/companies')
            ->assertOk();
    }

    public function test_product_requires_principal_and_valid_type(): void
    {
        $principal = MasterPrincipal::factory()->create();

        $this->actingAs($this->user(), 'sanctum')
            ->postJson('/api/v1/products', [
                'principal_id' => $principal->id,
                'name' => 'Server X',
                'type' => 'INVALID_TYPE',
                'price' => 1000000,
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_create_product(): void
    {
        $principal = MasterPrincipal::factory()->create();

        $this->actingAs($this->user(), 'sanctum')
            ->postJson('/api/v1/products', [
                'principal_id' => $principal->id,
                'name' => 'Server X',
                'type' => 'PRODUCT',
                'price' => 1000000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Server X');
    }

    public function test_user_crud_requires_admin(): void
    {
        $admin = $this->user();
        $sales = $this->user(Role::SALES->value);

        $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertOk();
    }

    public function test_roles_endpoint_available(): void
    {
        foreach (Role::cases() as $role) {
            RoleModel::create(['name' => $role->value, 'description' => $role->label()]);
        }

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }
}
