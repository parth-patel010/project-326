<?php

declare(strict_types=1);

/**
 * Shared GST / service-charge math for POS bills.
 */
function fm_bill_totals(array $items, float $discount, float $gstPercent, bool $gstEnabled, float $servicePercent): array
{
    $subtotal = 0.0;
    $taxable = 0.0;
    foreach ($items as $it) {
        $line = ((float) ($it['price'] ?? 0)) * ((int) ($it['qty'] ?? 1));
        $subtotal += $line;
        $inclusive = !empty($it['gst_inclusive']);
        if ($gstEnabled && !$inclusive) {
            $taxable += $line;
        }
    }
    $discount = max(0, min($discount, $subtotal));
    $afterDiscount = max(0, $subtotal - $discount);
    // Apply discount proportionally to taxable portion
    $taxableAfter = $subtotal > 0 ? $taxable * ($afterDiscount / $subtotal) : 0;
    $tax = $gstEnabled ? round($taxableAfter * ($gstPercent / 100), 2) : 0.0;
    $cgst = round($tax / 2, 2);
    $sgst = $tax - $cgst;
    $service = round($afterDiscount * (max(0, $servicePercent) / 100), 2);
    $total = round($afterDiscount + $tax + $service, 2);
    return [
        'subtotal' => round($subtotal, 2),
        'discount' => round($discount, 2),
        'taxable' => round($taxableAfter, 2),
        'tax' => $tax,
        'cgst' => $cgst,
        'sgst' => $sgst,
        'service_charge' => $service,
        'total' => $total,
    ];
}
