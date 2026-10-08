<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Company\CreateCompanyAction;
use App\Enums\CustomerStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
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

    public function test_owner_can_list_customers_with_pagination(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Customer::factory()->count(15)->create([
            'company_id' => $this->company->id,
            'status' => CustomerStatus::Active->value,
        ]);

        $response = $this->getJson("/api/v1/companies/{$this->company->id}/customers?per_page=10");

        $response->assertOk()
            ->assertJsonPath('data.total', 15)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonCount(10, 'data.data');
    }

    public function test_search_customers_by_name_email_and_company(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Johnathan Wick',
            'email' => 'wick@continental.test',
            'company_name' => 'Continental Hotel',
            'tax_number' => 'TAX-9988',
        ]);

        Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Bruce Wayne',
            'email' => 'bruce@wayne.test',
            'company_name' => 'Wayne Enterprises',
            'tax_number' => 'TAX-0011',
        ]);

        // Search by name
        $response = $this->getJson("/api/v1/companies/{$this->company->id}/customers?search=Johnathan");
        $response->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Johnathan Wick');

        // Search by company
        $response = $this->getJson("/api/v1/companies/{$this->company->id}/customers?search=Wayne");
        $response->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Bruce Wayne');

        // Search by tax number
        $response = $this->getJson("/api/v1/companies/{$this->company->id}/customers?search=9988");
        $response->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.name', 'Johnathan Wick');
    }

    public function test_filter_customers_by_status(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Customer::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => CustomerStatus::Active->value,
        ]);

        Customer::factory()->count(3)->archived()->create([
            'company_id' => $this->company->id,
        ]);

        // Default / active filter
        $responseActive = $this->getJson("/api/v1/companies/{$this->company->id}/customers?status=active");
        $responseActive->assertOk()->assertJsonCount(2, 'data.data');

        // Archived filter
        $responseArchived = $this->getJson("/api/v1/companies/{$this->company->id}/customers?status=archived");
        $responseArchived->assertOk()->assertJsonCount(3, 'data.data');

        // All filter
        $responseAll = $this->getJson("/api/v1/companies/{$this->company->id}/customers?status=all");
        $responseAll->assertOk()->assertJsonCount(5, 'data.data');
    }

    public function test_owner_can_create_customer(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $payload = [
            'name' => 'Globex Corporation',
            'company_name' => 'Globex Inc.',
            'email' => 'billing@globex.test',
            'phone' => '+1 555 123 4567',
            'tax_number' => 'US-991122',
            'billing_address' => '742 Evergreen Terrace',
            'billing_city' => 'Springfield',
            'billing_state' => 'OR',
            'billing_postal_code' => '97477',
            'billing_country' => 'USA',
            'shipping_same_as_billing' => true,
            'notes' => 'Important enterprise customer.',
        ];

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Globex Corporation')
            ->assertJsonPath('data.email', 'billing@globex.test')
            ->assertJsonPath('data.shipping_same_as_billing', true)
            ->assertJsonPath('data.shipping_address', '742 Evergreen Terrace')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('customers', [
            'company_id' => $this->company->id,
            'name' => 'Globex Corporation',
            'status' => CustomerStatus::Active->value,
            'shipping_same_as_billing' => true,
        ]);
    }

    public function test_admin_can_create_customer(): void
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();
        $this->company->users()->attach($admin->id, ['role_id' => $adminRole->id]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'Admin Created Customer',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Admin Created Customer');
    }

    public function test_accountant_and_member_cannot_create_customer(): void
    {
        $accountant = User::factory()->create();
        $accountantRole = Role::where('slug', Role::ACCOUNTANT)->firstOrFail();
        $this->company->users()->attach($accountant->id, ['role_id' => $accountantRole->id]);

        Sanctum::actingAs($accountant);

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'Unauthorized Customer',
        ]);

        $response->assertForbidden();

        $member = User::factory()->create();
        $memberRole = Role::where('slug', Role::MEMBER)->firstOrFail();
        $this->company->users()->attach($member->id, ['role_id' => $memberRole->id]);

        Sanctum::actingAs($member);

        $responseMember = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'Unauthorized Customer',
        ]);

        $responseMember->assertForbidden();
    }

    public function test_free_plan_enforces_limit_of_5_active_customers(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Company is on Free plan by default. Create 5 active customers
        Customer::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => CustomerStatus::Active->value,
        ]);

        // Attempting to create the 6th customer must be rejected with 403
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'Customer #6 Beyond Limit',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'You have reached the customer limit for your current plan.');

        $this->assertDatabaseMissing('customers', [
            'company_id' => $this->company->id,
            'name' => 'Customer #6 Beyond Limit',
        ]);
    }

    public function test_archiving_customer_frees_up_capacity_on_free_plan(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $customers = Customer::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => CustomerStatus::Active->value,
        ]);

        // 6th fails
        $this->postJson("/api/v1/companies/{$this->company->id}/customers", ['name' => '6th'])
            ->assertForbidden();

        // Archive 1 customer
        $firstCustomer = $customers->first();
        $this->postJson("/api/v1/companies/{$this->company->id}/customers/{$firstCustomer->id}/archive")
            ->assertOk();

        // Now creating a 5th active customer succeeds
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'New 5th Active Customer',
        ]);

        $response->assertCreated();
    }

    public function test_pro_plan_company_can_create_more_than_5_customers(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Upgrade company to Pro plan
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $this->company->subscriptions()->latest('id')->first()->update(['plan_id' => $proPlan->id]);

        Customer::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => CustomerStatus::Active->value,
        ]);

        // 6th customer succeeds without limit restriction
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'Customer #6 on Pro Plan',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Customer #6 on Pro Plan');
    }

    public function test_owner_can_view_and_update_customer(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Initial Name',
        ]);

        // View single customer
        $responseGet = $this->getJson("/api/v1/companies/{$this->company->id}/customers/{$customer->id}");
        $responseGet->assertOk()
            ->assertJsonPath('data.id', $customer->id)
            ->assertJsonPath('data.name', 'Initial Name');

        // Update customer
        $responseUpdate = $this->putJson("/api/v1/companies/{$this->company->id}/customers/{$customer->id}", [
            'name' => 'Updated Name',
            'phone' => '+1 800 555 0199',
            'company_id' => 9999, // Attempt to tamper company_id
        ]);

        $responseUpdate->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '+1 800 555 0199');

        $customer->refresh();
        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame($this->company->id, $customer->company_id, 'company_id must remain immutable');
    }

    public function test_owner_can_archive_and_restore_customer(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'status' => CustomerStatus::Active->value,
        ]);

        // Archive
        $archiveResponse = $this->postJson("/api/v1/companies/{$this->company->id}/customers/{$customer->id}/archive");
        $archiveResponse->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.is_archived', true);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $customer->refresh();
        $this->assertSame(CustomerStatus::Archived, $customer->status);

        // Restore
        $restoreResponse = $this->postJson("/api/v1/companies/{$this->company->id}/customers/{$customer->id}/restore");
        $restoreResponse->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_archived', false);

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
        $customer->refresh();
        $this->assertSame(CustomerStatus::Active, $customer->status);
    }

    public function test_cross_company_isolation_and_idor_prevention(): void
    {
        // Second user and second company
        $otherUser = User::factory()->create();
        /** @var CreateCompanyAction $createCompanyAction */
        $createCompanyAction = app(CreateCompanyAction::class);
        $otherCompany = $createCompanyAction->execute($otherUser, [
            'name' => 'Rival Industries',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ]);

        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Rival VIP Client',
        ]);

        Sanctum::actingAs($this->ownerUser);

        // Attempting to access otherCompany endpoint returns 403 Forbidden
        $this->getJson("/api/v1/companies/{$otherCompany->id}/customers/{$otherCustomer->id}")
            ->assertForbidden();

        // Attempting to access otherCompany customer under this.company returns 404
        $this->getJson("/api/v1/companies/{$this->company->id}/customers/{$otherCustomer->id}")
            ->assertNotFound();

        // Attempting to update otherCompany customer returns 404
        $this->putJson("/api/v1/companies/{$this->company->id}/customers/{$otherCustomer->id}", [
            'name' => 'Hacked Name',
        ])->assertNotFound();

        // Attempting to archive otherCompany customer returns 404
        $this->postJson("/api/v1/companies/{$this->company->id}/customers/{$otherCustomer->id}/archive")
            ->assertNotFound();

        // Listing customers only lists this company's customers
        $listResponse = $this->getJson("/api/v1/companies/{$this->company->id}/customers");
        $listResponse->assertOk();
        $ids = collect($listResponse->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($otherCustomer->id, $ids);
    }

    public function test_validation_errors_when_creating_customer(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Name missing
        $response = $this->postJson("/api/v1/companies/{$this->company->id}/customers", []);
        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);

        // Invalid email format
        $responseInvalidEmail = $this->postJson("/api/v1/companies/{$this->company->id}/customers", [
            'name' => 'Test Customer',
            'email' => 'invalid-email-address',
        ]);
        $responseInvalidEmail->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_safe_sorting_whitelist(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Alpha Customer',
            'created_at' => now()->subDays(2),
        ]);

        Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Omega Customer',
            'created_at' => now()->subDay(),
        ]);

        // Ascending by name
        $responseAsc = $this->getJson("/api/v1/companies/{$this->company->id}/customers?sort=name");
        $responseAsc->assertOk();
        $this->assertSame('Alpha Customer', $responseAsc->json('data.data.0.name'));

        // Descending by name
        $responseDesc = $this->getJson("/api/v1/companies/{$this->company->id}/customers?sort=-name");
        $responseDesc->assertOk();
        $this->assertSame('Omega Customer', $responseDesc->json('data.data.0.name'));
    }
}
