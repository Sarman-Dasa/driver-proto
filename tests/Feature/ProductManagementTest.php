<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Company\CreateCompanyAction;
use App\Enums\ProductStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerUser;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(PlanAndFeatureSeeder::class);

        $this->ownerUser = User::factory()->create();

        /** @var CreateCompanyAction $createCompanyAction */
        $createCompanyAction = app(CreateCompanyAction::class);
        $this->company = $createCompanyAction->execute($this->ownerUser, [
            'name' => 'Acme Corporation',
            'email' => 'contact@acme.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
    }

    public function test_owner_can_list_products_with_pagination(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Product::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        $response = $this->getJson("/api/v1/companies/{$this->company->id}/products?per_page=10");

        $response->assertOk()
            ->assertJsonPath('data.total', 15)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonCount(10, 'data.data');
    }

    public function test_search_products_by_name_description_and_sku(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Custom Website Development',
            'description' => 'Full-stack application development',
            'sku' => 'WEB-DEV-001',
        ]);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Ergonomic Office Chair',
            'description' => 'Comfortable seating for workstation',
            'sku' => 'FURN-CHR-002',
        ]);

        // Search by name
        $responseName = $this->getJson("/api/v1/companies/{$this->company->id}/products?search=Website");
        $responseName->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Custom Website Development');

        // Search by description
        $responseDesc = $this->getJson("/api/v1/companies/{$this->company->id}/products?search=workstation");
        $responseDesc->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Ergonomic Office Chair');

        // Search by SKU
        $responseSku = $this->getJson("/api/v1/companies/{$this->company->id}/products?search=WEB-DEV-001");
        $responseSku->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Custom Website Development');
    }

    public function test_filter_products_by_type_and_status(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Product::factory()->product()->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        Product::factory()->service()->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        Product::factory()->service()->inactive()->create([
            'company_id' => $this->company->id,
        ]);

        // Type filter: products
        $responseProducts = $this->getJson("/api/v1/companies/{$this->company->id}/products?type=product");
        $responseProducts->assertOk()->assertJsonCount(1, 'data.data');

        // Type filter: services
        $responseServices = $this->getJson("/api/v1/companies/{$this->company->id}/products?type=service");
        $responseServices->assertOk()->assertJsonCount(2, 'data.data');

        // Status filter: active
        $responseActive = $this->getJson("/api/v1/companies/{$this->company->id}/products?status=active");
        $responseActive->assertOk()->assertJsonCount(2, 'data.data');

        // Status filter: inactive
        $responseInactive = $this->getJson("/api/v1/companies/{$this->company->id}/products?status=inactive");
        $responseInactive->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_owner_can_create_product_and_service(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $payload = [
            'type' => 'service',
            'name' => 'Cloud Architecture Consulting',
            'description' => 'Architecture strategy and infrastructure design.',
            'sku' => 'SRV-ARCH-001',
            'price' => 250000, // $2,500.00
            'currency' => 'USD',
            'tax_rate' => 18.0,
            'unit' => 'hour',
            'status' => 'active',
        ];

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/products", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Cloud Architecture Consulting')
            ->assertJsonPath('data.type', 'service')
            ->assertJsonPath('data.sku', 'SRV-ARCH-001')
            ->assertJsonPath('data.price', 250000)
            ->assertJsonPath('data.price_decimal', 2500)
            ->assertJsonPath('data.tax_rate', 18)
            ->assertJsonPath('data.unit', 'hour')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('products', [
            'company_id' => $this->company->id,
            'name' => 'Cloud Architecture Consulting',
            'sku' => 'SRV-ARCH-001',
            'price' => 250000,
            'type' => 'service',
        ]);
    }

    public function test_admin_can_create_product(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();
        $this->company->users()->attach($admin->id, ['role_id' => $adminRole->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Admin Created Item',
            'price' => 5000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Admin Created Item');
    }

    public function test_accountant_and_member_cannot_create_or_delete_product(): void
    {
        $accountant = User::factory()->create();
        $accountantRole = Role::where('slug', Role::ACCOUNTANT)->firstOrFail();
        $this->company->users()->attach($accountant->id, ['role_id' => $accountantRole->id]);

        $product = Product::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($accountant);

        // Accountant can view catalog
        $this->getJson("/api/v1/companies/{$this->company->id}/products")->assertOk();
        $this->getJson("/api/v1/companies/{$this->company->id}/products/{$product->id}")->assertOk();

        // Accountant cannot create
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Accountant Item',
            'price' => 1000,
        ])->assertForbidden();

        // Accountant cannot delete
        $this->deleteJson("/api/v1/companies/{$this->company->id}/products/{$product->id}")->assertForbidden();

        // Member cannot create
        $member = User::factory()->create();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();
        $this->company->users()->attach($member->id, ['role_id' => $memberRole->id]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Member Item',
            'price' => 1000,
        ])->assertForbidden();
    }

    public function test_sku_uniqueness_is_enforced_per_company(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'sku' => 'UNIQUE-SKU-001',
        ]);

        // Same SKU in same company fails
        $responseDuplicate = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Duplicate SKU Item',
            'sku' => 'UNIQUE-SKU-001',
            'price' => 1000,
        ]);
        $responseDuplicate->assertUnprocessable()->assertJsonValidationErrors(['sku']);

        // Multiple products with null SKU are allowed
        $firstNull = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Null SKU Item 1',
            'sku' => null,
            'price' => 1000,
        ]);
        $firstNull->assertCreated();

        $secondNull = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Null SKU Item 2',
            'sku' => null,
            'price' => 1000,
        ]);
        $secondNull->assertCreated();
    }

    public function test_free_plan_enforces_product_limit_of_10(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Free plan default allows 10 items
        Product::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        // 11th product attempt must be rejected with 403
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => '11th Item Beyond Limit',
            'price' => 1000,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'You have reached the product and service limit for your current plan.');

        $this->assertDatabaseMissing('products', [
            'company_id' => $this->company->id,
            'name' => '11th Item Beyond Limit',
        ]);
    }

    public function test_deleting_or_inactivating_product_frees_up_capacity(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $products = Product::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        // 11th fails
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Item 11',
            'price' => 1000,
        ])->assertForbidden();

        // Soft delete 1 item
        $first = $products->first();
        $this->deleteJson("/api/v1/companies/{$this->company->id}/products/{$first->id}")->assertOk();

        // Now creating a 10th active item succeeds
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'New 10th Active Item',
            'price' => 1000,
        ]);

        $response->assertCreated();
    }

    public function test_pro_plan_company_has_unlimited_products(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Upgrade to Pro plan
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $this->company->subscriptions()->latest('id')->first()->update(['plan_id' => $proPlan->id]);

        Product::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        // 11th product succeeds
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'service',
            'name' => 'Item 11 on Pro Plan',
            'price' => 15000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Item 11 on Pro Plan');
    }

    public function test_owner_can_update_product_and_company_id_is_immutable(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Original Name',
            'price' => 1000,
        ]);

        $response = $this->putJson("/api/v1/companies/{$this->company->id}/products/{$product->id}", [
            'name' => 'Updated Product Name',
            'price' => 2500,
            'company_id' => 9999, // Attempt to tamper company_id
        ]);

        // Validation prohibits company_id or ignores it
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['company_id']);
        } else {
            $response->assertOk();
            $this->assertSame($this->company->id, $product->fresh()->company_id);
        }

        // Clean update without tampering
        $cleanResponse = $this->putJson("/api/v1/companies/{$this->company->id}/products/{$product->id}", [
            'name' => 'Updated Product Name',
            'price' => 2500,
        ]);

        $cleanResponse->assertOk()
            ->assertJsonPath('data.name', 'Updated Product Name')
            ->assertJsonPath('data.price', 2500);

        $this->assertSame('Updated Product Name', $product->fresh()->name);
        $this->assertSame($this->company->id, $product->fresh()->company_id);
    }

    public function test_owner_can_toggle_product_status(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'status' => ProductStatus::Active->value,
        ]);

        // Deactivate
        $responseDeactivate = $this->patchJson("/api/v1/companies/{$this->company->id}/products/{$product->id}/status", [
            'status' => 'inactive',
        ]);

        $responseDeactivate->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(ProductStatus::Inactive, $product->fresh()->status);

        // Reactivate
        $responseActivate = $this->patchJson("/api/v1/companies/{$this->company->id}/products/{$product->id}/status", [
            'status' => 'active',
        ]);

        $responseActivate->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
    }

    public function test_owner_can_soft_delete_and_restore_product(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Product to Delete',
        ]);

        // Soft delete
        $responseDelete = $this->deleteJson("/api/v1/companies/{$this->company->id}/products/{$product->id}");
        $responseDelete->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // Normal list does not show soft-deleted item
        $responseList = $this->getJson("/api/v1/companies/{$this->company->id}/products");
        $responseList->assertOk();
        $ids = collect($responseList->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($product->id, $ids);

        // Restore
        $responseRestore = $this->postJson("/api/v1/companies/{$this->company->id}/products/{$product->id}/restore");
        $responseRestore->assertOk()
            ->assertJsonPath('data.name', 'Product to Delete');

        $this->assertNotSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_cross_company_isolation_and_idor_prevention(): void
    {
        $otherUser = User::factory()->create();
        /** @var CreateCompanyAction $createCompanyAction */
        $createCompanyAction = app(CreateCompanyAction::class);
        $otherCompany = $createCompanyAction->execute($otherUser, [
            'name' => 'Other Corp',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ]);

        $otherProduct = Product::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Secret Other Product',
            'sku' => 'SHARED-SKU-001',
        ]);

        Sanctum::actingAs($this->ownerUser);

        // Company A cannot access otherCompany endpoint
        $this->getJson("/api/v1/companies/{$otherCompany->id}/products/{$otherProduct->id}")
            ->assertForbidden();

        // Company A cannot access otherProduct under own company endpoint
        $this->getJson("/api/v1/companies/{$this->company->id}/products/{$otherProduct->id}")
            ->assertNotFound();

        // Company A cannot update otherProduct
        $this->putJson("/api/v1/companies/{$this->company->id}/products/{$otherProduct->id}", [
            'name' => 'Hacked Item',
        ])->assertNotFound();

        // Company A cannot delete otherProduct
        $this->deleteJson("/api/v1/companies/{$this->company->id}/products/{$otherProduct->id}")
            ->assertNotFound();

        // SKU can be reused in Company A even though Company B used it
        $allowedReuse = $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Company A Same SKU Item',
            'sku' => 'SHARED-SKU-001',
            'price' => 5000,
        ]);
        $allowedReuse->assertCreated();
    }

    public function test_safe_sorting_whitelist(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Alpha Item',
            'price' => 1000,
        ]);

        Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Omega Item',
            'price' => 5000,
        ]);

        // Ascending by price
        $responseAsc = $this->getJson("/api/v1/companies/{$this->company->id}/products?sort=price");
        $responseAsc->assertOk();
        $this->assertSame('Alpha Item', $responseAsc->json('data.data.0.name'));

        // Descending by price
        $responseDesc = $this->getJson("/api/v1/companies/{$this->company->id}/products?sort=-price");
        $responseDesc->assertOk();
        $this->assertSame('Omega Item', $responseDesc->json('data.data.0.name'));
    }

    public function test_input_validation_rules(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Name missing
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'price' => 1000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        // Negative price
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Negative Price',
            'price' => -500,
        ])->assertUnprocessable()->assertJsonValidationErrors(['price']);

        // Invalid tax rate > 100
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'product',
            'name' => 'Invalid Tax',
            'price' => 1000,
            'tax_rate' => 150,
        ])->assertUnprocessable()->assertJsonValidationErrors(['tax_rate']);

        // Invalid type
        $this->postJson("/api/v1/companies/{$this->company->id}/products", [
            'type' => 'invalid_type',
            'name' => 'Invalid Type',
            'price' => 1000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['type']);
    }
}
