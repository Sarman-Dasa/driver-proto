<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiscountType;

class InvoiceCalculationService
{
    /**
     * Calculate line item financial totals.
     *
     * @return array{subtotal: int, tax_amount: int, total: int}
     */
    public function calculateItem(float $quantity, int $unitPrice, float $taxRate): array
    {
        $cleanQty = max(0.0, $quantity);
        $cleanPrice = max(0, $unitPrice);
        $cleanTaxRate = max(0.0, min(100.0, $taxRate));

        // Subtotal in minor integer cents
        $subtotal = (int) round($cleanQty * $cleanPrice, 0, PHP_ROUND_HALF_UP);

        // Line-level tax in minor integer cents
        $taxAmount = (int) round($subtotal * ($cleanTaxRate / 100), 0, PHP_ROUND_HALF_UP);

        $total = $subtotal + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /**
     * Calculate entire invoice financials deterministically from items, discount, and amount paid.
     *
     * @param  array<int, array{quantity: float|int, unit_price: int, tax_rate: float|int}>  $items
     * @return array{
     *     calculated_items: array<int, array{subtotal: int, tax_amount: int, total: int}>,
     *     subtotal: int,
     *     discount_amount: int,
     *     tax_amount: int,
     *     total_amount: int,
     *     balance_due: int
     * }
     */
    public function calculateInvoice(
        array $items,
        string|DiscountType $discountType = DiscountType::None,
        float $discountValue = 0.0,
        int $amountPaid = 0,
    ): array {
        $type = $discountType instanceof DiscountType ? $discountType : (DiscountType::tryFrom($discountType) ?? DiscountType::None);
        $cleanDiscountValue = max(0.0, $discountValue);
        $cleanAmountPaid = max(0, $amountPaid);

        $calculatedItems = [];
        $subtotal = 0;
        $totalTax = 0;

        foreach ($items as $item) {
            $itemCalc = $this->calculateItem(
                (float) ($item['quantity'] ?? 1.0),
                (int) ($item['unit_price'] ?? 0),
                (float) ($item['tax_rate'] ?? 0.0),
            );

            $calculatedItems[] = $itemCalc;
            $subtotal += $itemCalc['subtotal'];
            $totalTax += $itemCalc['tax_amount'];
        }

        // Calculate discount
        $discountAmount = 0;
        if ($type === DiscountType::Percentage) {
            $percentage = min(100.0, $cleanDiscountValue);
            $discountAmount = (int) round($subtotal * ($percentage / 100), 0, PHP_ROUND_HALF_UP);
        } elseif ($type === DiscountType::Fixed) {
            // Fixed discount value provided in major currency units or cents?
            // When stored, discount_value is recorded as numeric decimal (e.g. 50.00 = $50.00 = 5000 cents)
            // or if passed in cents: we treat discount_value decimal as major unit (e.g. 50 = $50.00 = 5000 cents)
            $fixedCents = (int) round($cleanDiscountValue * 100, 0, PHP_ROUND_HALF_UP);
            $discountAmount = min($subtotal, $fixedCents);
        }

        $totalAmount = max(0, $subtotal - $discountAmount + $totalTax);
        $balanceDue = max(0, $totalAmount - $cleanAmountPaid);

        return [
            'calculated_items' => $calculatedItems,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $totalTax,
            'total_amount' => $totalAmount,
            'balance_due' => $balanceDue,
        ];
    }
}
