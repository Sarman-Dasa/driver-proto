<?php

declare(strict_types=1);

namespace App\Actions\Invoice;

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\InvoiceCalculationService;
use App\Services\InvoiceNumberService;
use App\Services\UsageLimitService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceAction
{
    public function __construct(
        private readonly FeatureAccessService $featureAccessService,
        private readonly UsageLimitService $usageLimitService,
        private readonly InvoiceCalculationService $calculationService,
        private readonly InvoiceNumberService $numberService,
    ) {}

    /**
     * Execute invoice creation within a company-isolated database transaction.
     *
     * @param  array{
     *     customer_id: int,
     *     issue_date: string,
     *     due_date: string,
     *     currency?: string,
     *     discount_type?: string,
     *     discount_value?: float|int,
     *     notes?: ?string,
     *     terms?: ?string,
     *     items: array<int, array{
     *         product_id?: ?int,
     *         product_name: string,
     *         description?: ?string,
     *         quantity: float|int,
     *         unit?: ?string,
     *         unit_price: int,
     *         tax_rate?: float|int
     *     }>
     * }  $data
     */
    public function execute(Company $company, array $data, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($company, $data, $user) {
            // Lock company row to synchronize concurrent creates and guarantee sequence
            Company::query()->where('id', $company->id)->lockForUpdate()->first();

            // 1. Enforce Plan Feature Access
            if (! $this->featureAccessService->canUseFeature($company, Feature::INVOICES)) {
                throw new AuthorizationException('The Invoices feature is not available on your current plan.');
            }

            // 2. Enforce Monthly Invoices Usage Limit
            if (! $this->usageLimitService->canCreate($company, Feature::INVOICES)) {
                throw new AuthorizationException("You've reached your monthly plan limit for invoices. Upgrade to add more.");
            }

            // 3. Verify Customer belongs to current company
            /** @var Customer|null $customer */
            $customer = Customer::query()
                ->where('company_id', $company->id)
                ->where('id', $data['customer_id'])
                ->first();

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => ['The selected customer does not belong to this company.'],
                ]);
            }

            // 4. Validate referenced products belong to this company
            $productIds = collect($data['items'])
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($productIds)) {
                $validProductCount = Product::query()
                    ->where('company_id', $company->id)
                    ->whereIn('id', $productIds)
                    ->count();

                if ($validProductCount !== count($productIds)) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more selected products do not belong to this company.'],
                    ]);
                }
            }

            // 5. Generate safe unique invoice number
            $invoiceNumber = $this->numberService->generateNextNumber($company);

            // Normalize items unit prices into minor units (cents)
            $normalizedItems = [];
            foreach ($data['items'] as $item) {
                $rawPrice = $item['unit_price'] ?? 0;
                if (is_float($rawPrice) || (is_string($rawPrice) && str_contains((string) $rawPrice, '.'))) {
                    $item['unit_price'] = (int) round(((float) $rawPrice) * 100);
                } else {
                    $item['unit_price'] = (int) $rawPrice;
                }
                $normalizedItems[] = $item;
            }
            $data['items'] = $normalizedItems;

            // 6. Calculate invoice financial totals authoritatively
            $discountType = DiscountType::tryFrom($data['discount_type'] ?? 'none') ?? DiscountType::None;
            $discountValue = (float) ($data['discount_value'] ?? 0.0);

            $calculations = $this->calculationService->calculateInvoice(
                $data['items'],
                $discountType,
                $discountValue,
                0,
            );

            // 7. Snapshot customer billing details
            $customerSnapshot = [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company_name' => $customer->company_name,
                'tax_number' => $customer->tax_number,
                'billing_address' => $customer->billing_address,
                'billing_city' => $customer->billing_city,
                'billing_state' => $customer->billing_state,
                'billing_postal_code' => $customer->billing_postal_code,
                'billing_country' => $customer->billing_country,
                'shipping_address' => $customer->shipping_address,
                'shipping_city' => $customer->shipping_city,
                'shipping_state' => $customer->shipping_state,
                'shipping_postal_code' => $customer->shipping_postal_code,
                'shipping_country' => $customer->shipping_country,
            ];

            // 8. Create Invoice
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Draft,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'] ?? $company->currency ?? 'USD',
                'subtotal' => $calculations['subtotal'],
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $calculations['discount_amount'],
                'tax_amount' => $calculations['tax_amount'],
                'total_amount' => $calculations['total_amount'],
                'amount_paid' => 0,
                'balance_due' => $calculations['balance_due'],
                'customer_snapshot' => $customerSnapshot,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);

            // 9. Create Invoice Items with immutable line-level snapshots
            foreach ($data['items'] as $index => $itemData) {
                $itemCalc = $calculations['calculated_items'][$index];

                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $itemData['product_id'] ?? null,
                    'product_name' => $itemData['product_name'],
                    'description' => $itemData['description'] ?? null,
                    'quantity' => (float) $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? null,
                    'unit_price' => (int) $itemData['unit_price'],
                    'tax_rate' => (float) ($itemData['tax_rate'] ?? 0.0),
                    'tax_amount' => $itemCalc['tax_amount'],
                    'subtotal' => $itemCalc['subtotal'],
                    'total' => $itemCalc['total'],
                    'sort_order' => $index,
                ]);
            }

            return $invoice->load(['customer', 'items.product']);
        });
    }
}
