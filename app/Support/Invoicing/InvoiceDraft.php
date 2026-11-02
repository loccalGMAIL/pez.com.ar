<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceConcept;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\QuotePdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What an invoice proposes before it is issued. Each item records the charge
 * it bills, and nothing already invoiced is billed again: a charge invoiced
 * before it was paid is left out of its payment's invoice, and vice versa.
 *
 * @phpstan-type Item array{charge_id: ?int, description: string, detail: ?string, amount: float}
 * @phpstan-type Draft array{concept: InvoiceConcept, service_from: CarbonInterface, service_to: CarbonInterface, payment_due_on: ?CarbonInterface, items: list<Item>, total: float}
 */
class InvoiceDraft
{
    /**
     * The payment's charges, up to what each still lacks to be invoiced, and
     * what was left on account. $retrying is the payment's failed invoice.
     *
     * @return Draft
     */
    public static function forPayment(Payment $payment, ?Invoice $retrying = null): array
    {
        $payment->loadMissing('allocations.charge.subscription');

        $billed = $payment->allocations
            ->filter(fn (PaymentAllocation $allocation) => $allocation->charge !== null)
            ->map(fn (PaymentAllocation $allocation) => [
                'charge' => $allocation->charge,
                'amount' => min((float) $allocation->amount, $allocation->charge->pendingToInvoice($retrying)),
            ])
            ->filter(fn (array $line) => $line['amount'] > 0)
            ->values();

        $items = $billed
            ->map(fn (array $line) => [
                'charge_id' => $line['charge']->id,
                'description' => $line['charge']->concept,
                'detail' => $line['amount'] < $line['charge']->amount ? 'Pago parcial de '.QuotePdf::money($line['charge']->amount) : null,
                'amount' => $line['amount'],
            ])
            ->all();

        $unallocated = $payment->unallocated();

        if ($unallocated > 0) {
            $items[] = ['charge_id' => null, 'description' => 'Pago a cuenta', 'detail' => null, 'amount' => $unallocated];
        }

        return self::draft($billed->pluck('charge'), $payment->paid_on, null, array_values($items));
    }

    /**
     * What the charge still lacks to be invoiced, due when the charge is.
     * $retrying is the charge's failed invoice.
     *
     * @return Draft
     */
    public static function forCharge(Charge $charge, ?Invoice $retrying = null): array
    {
        $amount = $charge->pendingToInvoice($retrying);

        $items = $amount > 0 ? [[
            'charge_id' => $charge->id,
            'description' => $charge->concept,
            'detail' => $amount < $charge->amount ? 'Saldo de '.QuotePdf::money($charge->amount) : null,
            'amount' => $amount,
        ]] : [];

        return self::draft(collect([$charge]), $charge->issued_on, $charge->due_on, $items);
    }

    /**
     * @param  Collection<int, Charge>  $charges
     * @param  list<Item>  $items
     * @return Draft
     */
    private static function draft(Collection $charges, CarbonInterface $fallbackDate, ?CarbonInterface $paymentDueOn, array $items): array
    {
        // Subscriptions bill a month; other charges, the day they were issued.
        $from = $charges->map(fn (Charge $charge) => ($charge->period ?? $charge->issued_on)->copy()->startOfDay());
        $to = $charges->map(fn (Charge $charge) => $charge->period?->copy()->endOfMonth()->startOfDay() ?? $charge->issued_on->copy()->startOfDay());

        return [
            'concept' => self::concept($charges->pluck('is_resale')->all()),
            'service_from' => $from->min() ?? $fallbackDate->copy(),
            'service_to' => $to->max() ?? $fallbackDate->copy(),
            'payment_due_on' => $paymentDueOn?->copy(),
            'items' => $items,
            'total' => round(array_sum(array_column($items, 'amount')), 2),
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
