<?php

namespace App\Support\Arca;

use App\Models\Invoice;
use App\Rules\Cuit;
use App\Support\Settings;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR code ARCA requires on printed invoices (RG 4291): a link to ARCA's
 * verification page carrying the voucher data as base64 JSON.
 */
class InvoiceQr
{
    public const BASE_URL = 'https://www.afip.gob.ar/fe/qr/';

    /**
     * @return array{ver: int, fecha: string, cuit: int, ptoVta: int, tipoCmp: int, nroCmp: int, importe: float, moneda: string, ctz: int, tipoDocRec: int, nroDocRec: int, tipoCodAut: string, codAut: int}
     */
    public static function payload(Invoice $invoice): array
    {
        return [
            'ver' => 1,
            'fecha' => (string) $invoice->issued_on?->format('Y-m-d'),
            'cuit' => (int) Cuit::normalize((string) Settings::get('company.cuit')),
            'ptoVta' => $invoice->point_of_sale,
            'tipoCmp' => $invoice->type->value,
            'nroCmp' => (int) $invoice->number,
            'importe' => (float) $invoice->total,
            'moneda' => 'PES',
            'ctz' => 1,
            'tipoDocRec' => $invoice->recipient_document_type->value,
            'nroDocRec' => (int) $invoice->recipient_document_number,
            'tipoCodAut' => 'E',
            'codAut' => (int) $invoice->cae,
        ];
    }

    public static function url(Invoice $invoice): string
    {
        return self::BASE_URL.'?p='.base64_encode((string) json_encode(self::payload($invoice)));
    }

    /**
     * PNG as a data URI, ready for an <img> in the PDF.
     */
    public static function dataUri(Invoice $invoice): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'scale' => 6,
            'outputBase64' => true,
        ]);

        return (string) (new QRCode($options))->render(self::url($invoice));
    }
}
