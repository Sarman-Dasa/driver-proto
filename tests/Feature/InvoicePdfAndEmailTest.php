<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Company\CreateCompanyAction;
use App\Actions\Invoice\CreateInvoiceAction;
use App\Enums\InvoiceDeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceEmailJob;
use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InvoicePdfService;
use Database\Seeders\PlanAndFeatureSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePdfAndEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $ownerUser;

    private User $viewerUser;

    private Company $company;

    private Customer $customer;

    private Invoice $draftInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(PlanAndFeatureSeeder::class);

        $this->ownerUser = User::factory()->create();
        $this->viewerUser = User::factory()->create();

        /** @var CreateCompanyAction $createCompanyAction */
        $createCompanyAction = app(CreateCompanyAction::class);
        $this->company = $createCompanyAction->execute($this->ownerUser, [
            'name' => 'Acme Corporation',
            'email' => 'contact@acme.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        // Attach member to the company
        $memberRole = Role::query()->where('slug', Role::MEMBER)->first();
        $this->viewerUser->companies()->attach($this->company->id, ['role_id' => $memberRole?->id]);

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Jane Smith',
            'email' => 'jane@smithcorp.test',
            'company_name' => 'Smith Corp',
            'billing_address' => '456 Elm Street',
            'billing_city' => 'Gotham',
            'billing_state' => 'NJ',
            'billing_postal_code' => '07001',
            'billing_country' => 'USA',
        ]);

        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Cloud Migration Service',
            'price' => 150000,
            'tax_rate' => 10.0,
            'unit' => 'project',
        ]);

        /** @var CreateInvoiceAction $createInvoiceAction */
        $createInvoiceAction = app(CreateInvoiceAction::class);
        $this->draftInvoice = $createInvoiceAction->execute($this->company, [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'currency' => 'USD',
            'discount_type' => 'percentage',
            'discount_value' => 5.0,
            'notes' => 'Thank you for choosing Acme Corporation.',
            'terms' => 'Due in 30 days.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'description' => 'Lift and shift enterprise cloud migration',
                    'quantity' => 2,
                    'unit' => 'project',
                    'unit_price' => 150000,
                    'tax_rate' => 10.0,
                ],
            ],
        ], $this->ownerUser);
    }

    public function test_invoice_pdf_service_generates_valid_pdf_binary(): void
    {
        /** @var InvoicePdfService $pdfService */
        $pdfService = app(InvoicePdfService::class);
        $binary = $pdfService->generateBinary($this->draftInvoice);

        $this->assertNotEmpty($binary);
        // PDF files start with %PDF- header magic bytes
        $this->assertStringStartsWith('%PDF-', $binary);
    }

    public function test_pdf_generation_renders_long_invoices_with_multiple_items(): void
    {
        // Add 25 line items to simulate multi-page invoice
        for ($i = 1; $i <= 25; $i++) {
            $this->draftInvoice->items()->create([
                'product_name' => "Consulting Block {$i}",
                'description' => "Detailed engineering description for consulting block number {$i} with long description text.",
                'quantity' => 1,
                'unit' => 'hour',
                'unit_price' => 25000,
                'tax_rate' => 5.0,
                'tax_amount' => 1250,
                'subtotal' => 25000,
                'total' => 26250,
                'sort_order' => $i + 1,
            ]);
        }

        /** @var InvoicePdfService $pdfService */
        $pdfService = app(InvoicePdfService::class);
        $binary = $pdfService->generateBinary($this->draftInvoice->fresh(['items']));

        $this->assertNotEmpty($binary);
        $this->assertStringStartsWith('%PDF-', $binary);
    }

    public function test_authorized_user_can_download_invoice_pdf(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $response = $this->get("/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader(
            'Content-Disposition',
            "attachment; filename=\"Invoice-{$this->draftInvoice->invoice_number}.pdf\""
        );
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_viewer_with_view_permission_can_download_invoice_pdf(): void
    {
        Sanctum::actingAs($this->viewerUser);

        $response = $this->get("/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cross_company_pdf_download_is_forbidden(): void
    {
        $otherUser = User::factory()->create();
        /** @var CreateCompanyAction $createCompanyAction */
        $createCompanyAction = app(CreateCompanyAction::class);
        $otherCompany = $createCompanyAction->execute($otherUser, [
            'name' => 'Foreign Corp',
            'email' => 'foreign@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        Sanctum::actingAs($otherUser);

        // Attempting to download another company's invoice returns 403 or 404
        $response = $this->get("/api/v1/companies/{$otherCompany->id}/invoices/{$this->draftInvoice->id}/pdf");
        $response->assertNotFound();

        $response2 = $this->get("/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/pdf");
        $response2->assertForbidden();
    }

    public function test_owner_can_queue_invoice_email_delivery(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->ownerUser);

        $payload = [
            'recipient_email' => 'client@smithcorp.test',
            'subject' => 'Your invoice is ready',
            'message' => 'Please find attached your invoice.',
        ];

        $response = $this->postJson(
            "/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/email",
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipient_email', 'client@smithcorp.test')
            ->assertJsonPath('data.status', 'queued');

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $this->draftInvoice->id,
            'company_id' => $this->company->id,
            'recipient_email' => 'client@smithcorp.test',
            'status' => InvoiceDeliveryStatus::Queued->value,
        ]);

        Queue::assertPushed(SendInvoiceEmailJob::class);
    }

    public function test_email_validation_requires_valid_recipient(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $response = $this->postJson(
            "/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/email",
            [
                'recipient_email' => 'invalid-not-an-email',
                'subject' => '',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_email', 'subject']);
    }

    public function test_send_invoice_email_job_sends_email_with_pdf_and_updates_status(): void
    {
        Mail::fake();

        $delivery = InvoiceDelivery::create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->draftInvoice->id,
            'recipient_email' => 'jane@smithcorp.test',
            'subject' => 'Official Invoice',
            'message' => 'Please find attached invoice.',
            'status' => InvoiceDeliveryStatus::Queued,
            'attempts' => 0,
            'created_by' => $this->ownerUser->id,
        ]);

        $job = new SendInvoiceEmailJob($delivery->id);
        $job->handle(app(InvoicePdfService::class));

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $this->assertEquals('Official Invoice', $mail->subjectText);
            $this->assertEquals($this->draftInvoice->id, $mail->invoice->id);
            $this->assertNotEmpty($mail->pdfBinary);

            return true;
        });

        // Delivery marked as sent
        $delivery->refresh();
        $this->assertEquals(InvoiceDeliveryStatus::Sent, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        $this->assertEquals(1, $delivery->attempts);

        // Draft invoice transitioned to sent
        $this->draftInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Sent, $this->draftInvoice->status);
        $this->assertNotNull($this->draftInvoice->sent_at);
    }

    public function test_send_invoice_email_job_handles_failure_gracefully(): void
    {
        $delivery = InvoiceDelivery::create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->draftInvoice->id,
            'recipient_email' => 'fail@smithcorp.test',
            'subject' => 'Official Invoice',
            'message' => 'Please find attached invoice.',
            'status' => InvoiceDeliveryStatus::Queued,
            'attempts' => 0,
            'created_by' => $this->ownerUser->id,
        ]);

        $mockPdf = $this->createMock(InvoicePdfService::class);
        $mockPdf->method('generateBinary')->willThrowException(new \RuntimeException('PDF generation failure'));

        $job = new SendInvoiceEmailJob($delivery->id);

        try {
            $job->handle($mockPdf);
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable) {
            // Expected
        }

        $delivery->refresh();
        $this->assertEquals(InvoiceDeliveryStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
        $this->assertStringContainsString('PDF generation failure', (string) $delivery->error_message);
    }

    public function test_duplicate_send_prevention_rejects_rapid_duplicate_requests(): void
    {
        Sanctum::actingAs($this->ownerUser);

        InvoiceDelivery::create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->draftInvoice->id,
            'recipient_email' => 'client@smithcorp.test',
            'subject' => 'Your invoice',
            'status' => InvoiceDeliveryStatus::Queued,
            'attempts' => 0,
            'created_at' => now(),
        ]);

        $response = $this->postJson(
            "/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/email",
            [
                'recipient_email' => 'client@smithcorp.test',
                'subject' => 'Your invoice',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_email']);
    }

    public function test_cannot_send_cancelled_invoice_via_email(): void
    {
        Sanctum::actingAs($this->ownerUser);

        $this->draftInvoice->update(['status' => InvoiceStatus::Cancelled]);

        $response = $this->postJson(
            "/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/email",
            [
                'recipient_email' => 'client@smithcorp.test',
                'subject' => 'Your invoice',
            ]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice']);
    }

    public function test_user_can_fetch_invoice_delivery_logs(): void
    {
        Sanctum::actingAs($this->ownerUser);

        InvoiceDelivery::create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->draftInvoice->id,
            'recipient_email' => 'first@test.com',
            'subject' => 'First attempt',
            'status' => InvoiceDeliveryStatus::Sent,
            'attempts' => 1,
            'sent_at' => now(),
        ]);

        InvoiceDelivery::create([
            'company_id' => $this->company->id,
            'invoice_id' => $this->draftInvoice->id,
            'recipient_email' => 'second@test.com',
            'subject' => 'Second attempt',
            'status' => InvoiceDeliveryStatus::Failed,
            'attempts' => 1,
            'failed_at' => now(),
            'error_message' => 'Connection refused',
        ]);

        $response = $this->getJson("/api/v1/companies/{$this->company->id}/invoices/{$this->draftInvoice->id}/deliveries");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }
}
