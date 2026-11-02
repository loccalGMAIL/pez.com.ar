<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceConcept;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\QuotePdf;
use Carbon\CarbonInterface;

/**
 * What an invoice for a payment proposes before it is issued: one item per
 * charge the payment settles plus what was left on account, so the total
 * is always the payment's amount.
 */
class InvoiceDraft
{
    /**
     * @return array{concept: InvoiceConcept, service_from: CarbonInterface, service_to: CarbonInterface, items: list<array{description: string, detail: ?string, amount: float}>}
     */
    public static function for(Payment $payment): array
    {
        $payment->loadMissing('allocations.charge');

        $allocations = $payment->allocations->filter(fn (PaymentAllocation $allocation) => $allocation->charge !== null)->values();
        $charges = $allocations->map(fn (PaymentAllocation $allocation) => $allocation->charge);

        $items = $allocations
            ->map(fn (PaymentAllocation $allocation) => [
                'description' => $allocation->charge->concept,
                'detail' => $allocation->amount < $allocation->charge->amount
                    ? 'Pago parcial de '.QuotePdf::money($allocation->charge->amount)
                    : null,
                'amount' => (float) $allocation->amount,
            ])
            ->values()
            ->all();

        $unallocated = $payment->unallocated();

        if ($unallocated > 0) {
            $items[] = ['description' => 'Pago a cuenta', 'detail' => null, 'amount' => $unallocated];
        }

        // Subscriptions bill a month; other charges, the day they were issued.
        $from = $charges->map(fn (Charge $charge) => ($charge->period ?? $charge->issued_on)->copy()->startOfDay());
        $to = $charges->map(fn (Charge $charge) => $charge->period?->copy()->endOfMonth()->startOfDay() ?? $charge->issued_on->copy()->startOfDay());

        return [
            'concept' => self::concept($charges->pluck('is_resale')->all()),
            'service_from' => $from->min() ?? $payment->paid_on->copy(),
            'service_to' => $to->max() ?? $payment->paid_on->copy(),
            'items' => array_values($items),
        ];
    }

    /**
     * Resale charges are products; everything else is a service.
     *
     * @param  array<int, bool|null>  $resale
     */
    private static function concept(array $resale): InvoiceConcept
    {
        $products = count(array_filter($resale));

        return match (true) {
            $resale === [] || $products === 0 => InvoiceConcept::Services,
            $products === count($resale) => InvoiceConcept::Products,
            default => InvoiceConcept::Both,
        };
    }
}
