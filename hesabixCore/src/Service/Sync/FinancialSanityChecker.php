<?php

namespace App\Service\Sync;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FinancialSanityChecker
{
    /**
     * Validates invoice line items and totals to ensure financial integrity
     */
    public function validateInvoice(array $payload): void
    {
        if (empty($payload['items']) || !is_array($payload['items'])) {
            throw new BadRequestHttpException('Invoice items array cannot be empty');
        }

        $calculatedTotal = 0;
        foreach ($payload['items'] as $index => $item) {
            $unitPrice = $item['unitPrice'] ?? 0;
            $quantity = $item['quantity'] ?? 0;
            $discount = $item['discount'] ?? 0;
            $tax = $item['tax'] ?? 0;

            if ($quantity <= 0) {
                throw new BadRequestHttpException("Item #{$index}: Quantity must be greater than zero");
            }
            if ($unitPrice < 0) {
                throw new BadRequestHttpException("Item #{$index}: Unit price cannot be negative");
            }

            $lineTotal = ($unitPrice * $quantity) - $discount + $tax;
            $calculatedTotal += $lineTotal;
        }

        $declaredTotal = $payload['totalAmount'] ?? null;
        $headerDisc = (float)($payload['totalDiscount'] ?? 0);
        $shipping = (float)($payload['shippingCost'] ?? 0);
        $expected = $calculatedTotal - $headerDisc + $shipping;
        if ($declaredTotal !== null && abs($expected - (float)$declaredTotal) > 10) {
            throw new BadRequestHttpException("Financial mismatch: Total calculated amount ({$expected}) does not match declared total ({$declaredTotal})");
        }
    }
}
