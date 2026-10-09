<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Company\CreateCompanyAction;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerUser;

    private Company $company;

    private Customer $customer;

    private Product $product;

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

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'company_name' => 'Doe Enterprises',
            'billing_address' => '123 Main Street',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
        ]);

        $this->product = Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Web Development',
            'price' => 200000, // $2,000.00
            'tax_rate' => 10.00,
            'unit' => 'project',
        ]);
    }

    public function test_owner_can_create_draft_invoice_with_items_and_snapshots(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $payload = [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency' => 'USD',
            'discount_type' => 'percentage',
            'discount_value' => 10.0,
            'notes' => 'Thank you for your business!',
            'terms' => 'Net 30',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'description' => 'Full-stack application development',
                    'quantity' => 1.5,
                    'unit' => 'project',
                    'unit_price' => 200000,
                    'tax_rate' => 10.0,
                ],
                [
                    'product_id' => null,
                    'product_name' => 'Server Setup Fee',
                    'description' => 'One-time server deployment',
                    'quantity' => 1,
                    'unit' => 'service',
                    'unit_price' => 50000, // $500.00
                    'tax_rate' => 0.0,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.invoice_number', 'INV-0001')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.customer_snapshot.name', 'John Doe')
            ->assertJsonPath('data.customer_snapshot.company_name', 'Doe Enterprises');

        // Subtotal item 1: 1.5 * 200000 = 300000. Tax: 10% = 30000.
        // Subtotal item 2: 1 * 50000 = 50000. Tax: 0.
        // Total Subtotal = 350000 ($3,500.00).
        // Discount 10% of 350000 = 35000 ($350.00).
        // Total Tax = 30000 ($300.00).
        // Total Amount = 350000 - 35000 + 30000 = 345000 ($3,450.00).
        $response->assertJsonPath('data.subtotal', 350000)
            ->assertJsonPath('data.discount_amount', 35000)
            ->assertJsonPath('data.tax_amount', 30000)
            ->assertJsonPath('data.total_amount', 345000)
            ->assertJsonPath('data.balance_due', 345000);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-0001',
            'status' => 'draft',
            'total_amount' => 345000,
        ]);

        $this->assertDatabaseCount('invoice_items', 2);
    }

    public function test_invoice_numbers_are_automatically_generated_and_unique_per_company(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $payload = [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(15)->format('Y-m-d'),
            'items' => [
                [
                    'product_name' => 'Consulting',
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ],
        ];

        $resp1 = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);
        $resp1->assertCreated()->assertJsonPath('data.invoice_number', 'INV-0001');

        $resp2 = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);
        $resp2->assertCreated()->assertJsonPath('data.invoice_number', 'INV-0002');

        // Other company starts its own sequence at INV-0001
        /** @var CreateCompanyAction $createCompanyAction */
        $createCompanyAction = app(CreateCompanyAction::class);
        $otherCompany = $createCompanyAction->execute($this->ownerUser, [
            'name' => 'Second Workspace',
            'email' => 'second@acme.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        $otherPayload = $payload;
        $otherPayload['customer_id'] = $otherCustomer->id;

        $respOther = $this->postJson("/api/v1/companies/{$otherCompany->id}/invoices", $otherPayload);
        $respOther->assertCreated()->assertJsonPath('data.invoice_number', 'INV-0001');
    }

    public function test_invoice_list_pagination_search_and_filters(): void
    {
        Sanctum::actingAs($this->ownerUser);

        Invoice::factory()->count(12)->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
        ]);

        Invoice::factory()->count(3)->sent()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        // Default pagination: 15 items total, 10 per page
        $res = $this->getJson("/api/v1/companies/{$this->company->id}/invoices?per_page=10");
        $res->assertOk()
            ->assertJsonPath('data.total', 15)
            ->assertJsonPath('data.per_page', 10);

        // Filter by status=sent
        $resSent = $this->getJson("/api/v1/companies/{$this->company->id}/invoices?status=sent");
        $resSent->assertOk()
            ->assertJsonPath('data.total', 3);

        // Filter by status=draft
        $resDraft = $this->getJson("/api/v1/companies/{$this->company->id}/invoices?status=draft");
        $resDraft->assertOk()
            ->assertJsonPath('data.total', 12);
    }

    public function test_owner_can_view_invoice_details(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->getJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_owner_can_update_draft_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'balance_due' => 10000,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'unit_price' => 10000]);

        $updatePayload = [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-11-01',
            'due_date' => '2026-11-20',
            'discount_type' => 'fixed',
            'discount_value' => 20.0, // $20.00 off
            'items' => [
                [
                    'product_name' => 'Updated Service',
                    'quantity' => 2,
                    'unit_price' => 5000, // 2 * $50 = $100.00 (10000 cents)
                    'tax_rate' => 0,
                ],
            ],
        ];

        $response = $this->putJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}", $updatePayload);

        // 10000 subtotal - 2000 fixed discount = 8000 total
        $response->assertOk()
            ->assertJsonPath('data.subtotal', 10000)
            ->assertJsonPath('data.discount_amount', 2000)
            ->assertJsonPath('data.total_amount', 8000)
            ->assertJsonPath('data.balance_due', 8000);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total_amount' => 8000,
        ]);
    }

    public function test_cannot_update_sent_or_cancelled_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->sent()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->putJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}", [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-11-01',
            'due_date' => '2026-11-20',
            'items' => [
                ['product_name' => 'Item', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_owner_can_send_draft_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
        ]);

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}/send");

        $response->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'sent',
        ]);
        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_owner_can_cancel_draft_or_sent_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->sent()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'cancelled',
        ]);
        $this->assertNotNull($invoice->fresh()->cancelled_at);
    }

    public function test_owner_can_soft_delete_and_restore_draft_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
        ]);

        $deleteResponse = $this->deleteJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}");
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);

        $restoreResponse = $this->postJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}/restore");
        $restoreResponse->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertNotSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_cannot_delete_sent_invoice(): void
    {
        Sanctum::actingAs($this->ownerUser);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->sent()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->deleteJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}");
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_create_invoice_for_another_companys_customer(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        $payload = [
            'customer_id' => $otherCustomer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                ['product_name' => 'Service', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ];

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_cannot_create_invoice_with_another_companys_product(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $otherCompany = Company::factory()->create();
        $otherProduct = Product::factory()->create(['company_id' => $otherCompany->id]);

        $payload = [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $otherProduct->id,
                    'product_name' => 'Stolen Item',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ];

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    public function test_cross_company_idor_protection(): void
    {
        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);
        /** @var Invoice $otherInvoice */
        $otherInvoice = Invoice::factory()->create([
            'company_id' => $otherCompany->id,
            'customer_id' => $otherCustomer->id,
        ]);

        Sanctum::actingAs($this->ownerUser);

        // Attempt show
        $this->getJson("/api/v1/companies/{$this->company->id}/invoices/{$otherInvoice->id}")
            ->assertNotFound();

        // Attempt update
        $this->putJson("/api/v1/companies/{$this->company->id}/invoices/{$otherInvoice->id}", [
            'customer_id' => $this->customer->id,
            'issue_date' => '2026-11-01',
            'due_date' => '2026-11-20',
            'items' => [['product_name' => 'Item', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertNotFound();

        // Attempt delete
        $this->deleteJson("/api/v1/companies/{$this->company->id}/invoices/{$otherInvoice->id}")
            ->assertNotFound();

        // Attempt send
        $this->postJson("/api/v1/companies/{$this->company->id}/invoices/{$otherInvoice->id}/send")
            ->assertNotFound();

        // Attempt cancel
        $this->postJson("/api/v1/companies/{$this->company->id}/invoices/{$otherInvoice->id}/cancel")
            ->assertNotFound();
    }

    public function test_role_permissions_enforced_for_invoices(): void
    {
        // Create user with Member role (has invoices.view, but not invoices.delete or invoices.send)
        $memberUser = User::factory()->create();
        /** @var Role $memberRole */
        $memberRole = Role::where('slug', Role::MEMBER)->first();
        $memberUser->companies()->attach($this->company->id, ['role_id' => $memberRole->id]);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
        ]);

        Sanctum::actingAs($memberUser);

        // Can view
        $this->getJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}")
            ->assertOk();

        // Denied send
        $this->postJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}/send")
            ->assertForbidden();

        // Denied delete
        $this->deleteJson("/api/v1/companies/{$this->company->id}/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_plan_usage_limit_blocks_creation_when_monthly_limit_reached(): void
    {
        Sanctum::actingAs($this->ownerUser);

        // Free plan limit is 10 invoices per month
        Invoice::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => InvoiceStatus::Draft->value,
            'created_at' => now(),
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                ['product_name' => '11th Invoice', 'quantity' => 1, 'unit_price' => 5000],
            ],
        ];

        $response = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);
        $response->assertForbidden();
    }

    public function test_historical_invoice_item_snapshots_persist_even_when_product_price_changes(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $payload = [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_name' => $this->product->name,
                    'quantity' => 1,
                    'unit_price' => $this->product->price, // 200000 = $2,000.00
                    'tax_rate' => $this->product->tax_rate,
                ],
            ],
        ];

        $res = $this->postJson("/api/v1/companies/{$this->company->id}/invoices", $payload);
        $res->assertCreated();

        $invoiceId = $res->json('data.id');

        // Mutate original product price and tax
        $this->product->update([
            'price' => 350000, // changed to $3,500.00
            'tax_rate' => 20.0,
            'name' => 'New Service Name',
        ]);

        // Verify that the invoice and line item retained original values
        $fetchRes = $this->getJson("/api/v1/companies/{$this->company->id}/invoices/{$invoiceId}");
        $fetchRes->assertOk()
            ->assertJsonPath('data.items.0.product_name', 'Web Development')
            ->assertJsonPath('data.items.0.unit_price', 200000)
            ->assertJsonPath('data.items.0.tax_rate', 10)
            ->assertJsonPath('data.total_amount', 220000);
    }
}
