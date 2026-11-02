<?php

namespace App\Models;

use App\Casts\Money;
use App\Enums\InvoiceConcept;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RecipientDocumentType;
use App\Enums\TaxCondition;
use App\Rules\Cuit;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

/**
 * Fiscal document authorized by ARCA: a Factura C issued from a payment or
 * from a charge, or the Nota de crédito C that annuls one. It keeps its own
 * copy of the recipient and the items, so later edits to the client, the
 * payment or the charge never change it. Once authorized it is never edited
 * or deleted; it is annulled with a credit note.
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'payment_id',
        'charge_id',
        'credited_invoice_id',
        'environment',
        'type',
        'point_of_sale',
        'number',
        'issued_on',
        'concept',
        'service_from',
        'service_to',
        'payment_due_on',
        'recipient_document_type',
        'recipient_document_number',
        'recipient_tax_condition',
        'recipient_name',
        'recipient_address',
        'total',
        'status',
        'cae',
        'cae_due_on',
        'messages',
        'arca_response',
        'pdf_path',
    ];

    protected $attributes = [
        'status' => 'pendiente',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'concept' => InvoiceConcept::class,
            'status' => InvoiceStatus::class,
            'recipient_document_type' => RecipientDocumentType::class,
            'recipient_tax_condition' => TaxCondition::class,
            'total' => Money::class,
            'issued_on' => 'date',
            'service_from' => 'date',
            'service_to' => 'date',
            'payment_due_on' => 'date',
            'cae_due_on' => 'date',
            'messages' => 'array',
            'arca_response' => 'array',
        ];
    }

    public function isAuthorized(): bool
    {
        return $this->status === InvoiceStatus::Authorized;
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::NotaCreditoC;
    }

    /**
     * Annulled by an authorized credit note.
     */
    public function isCredited(): bool
    {
        return (bool) $this->creditNote?->isAuthorized();
    }

    /**
     * An authorized invoice that no credit note annulled.
     */
    public function isInForce(): bool
    {
        return ! $this->isCreditNote() && $this->isAuthorized() && ! $this->isCredited();
    }

    /**
     * Sent to ARCA without an answer: it has to be looked up as it was sent, so it cannot change.
     */
    public function isAwaitingArca(): bool
    {
        return ! $this->isAuthorized() && $this->number !== null && $this->environment === config('arca.environment');
    }

    /**
     * Point of sale and number as printed: 00003-00000125.
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function code(): Attribute
    {
        return Attribute::get(fn () => sprintf(
            '%05d-%s',
            $this->point_of_sale,
            $this->number === null ? 'S/N' : sprintf('%08d', $this->number),
        ));
    }

    /**
     * Type and number: "Factura C 00003-00000125".
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function title(): Attribute
    {
        return Attribute::get(fn () => "{$this->type->getLabel()} {$this->code}");
    }

    /**
     * Recipient document as printed: the CUIT with dashes, the DNI as is.
     *
     * @return Attribute<?string, never>
     */
    protected function recipientDocument(): Attribute
    {
        return Attribute::get(fn () => match ($this->recipient_document_type) {
            RecipientDocumentType::Cuit => Cuit::format($this->recipient_document_number),
            RecipientDocumentType::Dni => $this->recipient_document_number,
            default => null,
        });
    }

    /**
     * Link the client can open without logging in; the signature prevents guessing other invoices.
     */
    public function publicPdfUrl(): string
    {
        return URL::signedRoute('invoices.pdf', $this);
    }

    /**
     * Invoices (not credit notes) that count as invoiced: authorized or on
     * their way to ARCA, and not annulled. Rejected ones never count.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeCounting(Builder $query): void
    {
        self::whereCounting($query);
    }

    /**
     * The "counting" constraints on any invoices query, as relation
     * constraints need them; $except leaves out an invoice being retried.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public static function whereCounting(Builder $query, ?Invoice $except = null): Builder
    {
        return $query->where('type', InvoiceType::FacturaC)
            ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Authorized])
            ->whereDoesntHave('creditNote', fn (Builder $note) => $note->where('status', InvoiceStatus::Authorized))
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except?->getKey()));
    }

    /**
     * Invoices of the ARCA environment in use: test invoices never mix with real ones.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeCurrentEnvironment(Builder $query): void
    {
        $query->where('environment', config('arca.environment'));
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Charge invoiced before it was paid.
     *
     * @return BelongsTo<Charge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /**
     * On a credit note, the invoice it annuls.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credited_invoice_id');
    }

    /**
     * The latest credit note issued (or attempted) for this invoice.
     *
     * @return HasOne<Invoice, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(Invoice::class, 'credited_invoice_id')->latestOfMany();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }
}
