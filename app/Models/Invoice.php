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
use Illuminate\Support\Facades\URL;

/**
 * Fiscal document authorized by ARCA. It keeps its own copy of the recipient
 * and the items, so later edits to the client or the payment never change it.
 * Once authorized it is never edited or deleted.
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'payment_id',
        'environment',
        'type',
        'point_of_sale',
        'number',
        'issued_on',
        'concept',
        'service_from',
        'service_to',
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
            'cae_due_on' => 'date',
            'messages' => 'array',
            'arca_response' => 'array',
        ];
    }

    public function isAuthorized(): bool
    {
        return $this->status === InvoiceStatus::Authorized;
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
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }
}
