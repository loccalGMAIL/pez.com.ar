@php
    use App\Support\AmountInWords;
    use App\Support\QuotePdf;

    $legalName = $issuer['legal_name'] ?: ($company['owner'] ?: $company['name']);
    $isTest = $invoice->environment !== 'produccion';
    // ARCA never takes a payment due date before the voucher's date.
    $paymentDueOn = $invoice->payment_due_on && $invoice->issued_on && $invoice->payment_due_on->gt($invoice->issued_on)
        ? $invoice->payment_due_on
        : $invoice->issued_on;
@endphp

@extends('pdf.layout', [
    'kicker' => $invoice->isCreditNote() ? 'NOTA DE CRÉDITO ELECTRÓNICA' : 'FACTURA ELECTRÓNICA',
    'heading' => $invoice->type->title(),
    'meta' => array_filter([
        'N.º' => $invoice->code,
        'FECHA' => $invoice->issued_on?->format('d/m/Y'),
        'CUIT' => $company['cuit'],
        'ING. BRUTOS' => $issuer['gross_income'],
        'INICIO ACT.' => $issuer['activity_start'] ? \Illuminate\Support\Carbon::parse($issuer['activity_start'])->format('d/m/Y') : null,
    ]),
])

@section('title', $invoice->title)

@section('styles')
        /* Room at the foot of the page for the fiscal box, above the footer. */
        @page { margin: 32px 0 162px 0; }
        .footer { bottom: -162px; }
        .header { height: 104px; }
        .meta { width: 170px; }
        .content { padding-top: 26px; }

        /* Letter box ARCA requires at the top center, hanging over the header edge. */
        .letter { position: absolute; top: 34px; left: 50%; margin-left: -29px; width: 58px; height: 64px; background: #fff; border: 2.5px solid #27fb6b; border-radius: 7px; text-align: center; }
        .letter .l { font-size: 30px; font-weight: bold; color: #0a2e36; line-height: 32px; margin-top: 7px; }
        .letter .c { margin-top: 3px; font-size: 7px; font-weight: bold; color: #1fa84f; letter-spacing: .3px; }

        .parties { width: 100%; margin-bottom: 14px; }
        .parties > tbody > tr > td { width: 50%; vertical-align: top; }
        .parties td.left { padding-right: 18px; }
        .parties td.right { padding-left: 18px; border-left: 1px solid #c9d3d5; }
        .party-name { font-weight: bold; font-size: 11px; margin-bottom: 4px; }
        .facts td { padding: 1.5px 0; font-size: 9px; vertical-align: top; }
        .facts .k { color: #5b6f73; width: 92px; }
        .facts .v { font-weight: bold; }

        .strip { width: 100%; background: #f2f5f2; border-radius: 7px; margin-bottom: 16px; }
        .strip td { padding: 8px 16px; width: 25%; }
        .strip .k { color: #5b6f73; font-size: 7.5px; }
        .strip .v { font-weight: bold; font-size: 10px; margin-top: 1px; }

        .unit { text-align: right; width: 100px; white-space: nowrap; }
        .summary td.summary-left { padding-top: 12px; }

        .fiscal-wrap { position: fixed; bottom: -88px; left: 62px; right: 62px; }
        .fiscal { width: 100%; border: 1.5px solid #0a2e36; border-radius: 7px; }
        .fiscal td { vertical-align: middle; padding: 10px 14px; }
        .fiscal .qr { width: 46px; }
        .fiscal .qr img { width: 46px; height: 46px; }
        .fiscal .badge { color: #1fa84f; font-weight: bold; font-size: 11px; }
        .fiscal .badge-sub { color: #5b6f73; font-size: 8px; margin-top: 2px; }
        .fiscal .cae { width: 210px; border-left: 1px solid #c9d3d5; }
        .fiscal .cae table td { padding: 2px 0; }
        .fiscal .cae .k { color: #5b6f73; font-size: 8.5px; }
        .fiscal .cae .v { text-align: right; font-weight: bold; font-size: 11px; letter-spacing: .3px; }

        .watermark { position: fixed; top: 330px; left: 0; right: 0; text-align: center; white-space: nowrap; font-size: 34px; font-weight: bold; color: #0a2e36; opacity: .07; transform: rotate(-28deg); }
@endsection

@section('header-extra')
    @if ($isTest)
        <div class="watermark">HOMOLOGACIÓN · SIN VALIDEZ FISCAL</div>
    @endif

    <div class="letter">
        <div class="l">{{ $invoice->type->letter() }}</div>
        <div class="c">COD. {{ str_pad((string) $invoice->type->value, 2, '0', STR_PAD_LEFT) }}</div>
    </div>
@endsection

@section('content')
        <table class="parties">
            <tr>
                <td class="left">
                    <div class="section-title">EMISOR</div>
                    <div class="party-name">{{ $legalName }}</div>
                    <table class="facts">
                        @if ($legalName !== $company['name'])
                            <tr><td class="k">Nombre comercial</td><td class="v">{{ $company['name'] }}</td></tr>
                        @endif
                        <tr><td class="k">Condición IVA</td><td class="v">Responsable Monotributo</td></tr>
                        <tr><td class="k">Domicilio</td><td class="v">{{ $issuer['address'] ?: $company['address'] }}&nbsp;</td></tr>
                    </table>
                </td>
                <td class="right">
                    <div class="section-title">CLIENTE</div>
                    <div class="party-name">{{ $invoice->recipient_name }}</div>
                    <table class="facts">
                        @if ($invoice->recipient_document)
                            <tr><td class="k">{{ $invoice->recipient_document_type->getLabel() }}</td><td class="v">{{ $invoice->recipient_document }}</td></tr>
                        @endif
                        <tr><td class="k">Condición IVA</td><td class="v">{{ $invoice->recipient_tax_condition->getLabel() }}</td></tr>
                        <tr><td class="k">Domicilio</td><td class="v">{{ $invoice->recipient_address }}&nbsp;</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="strip">
            <tr>
                <td><div class="k">CONCEPTO</div><div class="v">{{ $invoice->concept->getLabel() }}</div></td>
                @if ($invoice->concept->hasServicePeriod())
                    <td><div class="k">PERÍODO DESDE</div><div class="v">{{ $invoice->service_from?->format('d/m/Y') }}</div></td>
                    <td><div class="k">PERÍODO HASTA</div><div class="v">{{ $invoice->service_to?->format('d/m/Y') }}</div></td>
                    <td><div class="k">VTO. DEL PAGO</div><div class="v">{{ $paymentDueOn?->format('d/m/Y') }}</div></td>
                @else
                    <td></td><td></td><td></td>
                @endif
            </tr>
        </table>

        <div class="section-title">DETALLE</div>
        <table class="items">
            <thead>
                <tr>
                    <th class="first">Concepto</th>
                    <th class="qty">Cant.</th>
                    <th class="amount">P. unitario</th>
                    <th class="amount last">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr class="{{ $loop->odd ? '' : 'alt' }} {{ $loop->last ? 'last-row' : '' }}">
                        <td class="first">
                            <div class="item-title">{{ $item->description }}</div>
                            @if ($item->detail)
                                <div class="item-desc">{{ $item->detail }}</div>
                            @endif
                        </td>
                        <td class="qty">1</td>
                        <td class="unit">{{ QuotePdf::money($item->amount) }}</td>
                        <td class="amount">{{ QuotePdf::money($item->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td class="summary-left">
                    <div class="in-words" style="margin-top: 0;">Son <strong>{{ AmountInWords::pesos($invoice->total) }}</strong>.</div>
                    @if ($invoice->creditedInvoice)
                        <div class="footnote">Anula la {{ $invoice->creditedInvoice->title }} del {{ $invoice->creditedInvoice->issued_on?->format('d/m/Y') }}.</div>
                    @elseif ($invoice->payment)
                        <div class="footnote">Cobrado con el recibo {{ $invoice->payment->code }} ({{ $invoice->payment->method->getLabel() }}).</div>
                    @elseif ($invoice->charge_id)
                        <div class="footnote">Vence el {{ $paymentDueOn?->format('d/m/Y') }}.</div>
                    @endif
                </td>
                <td class="summary-right">
                    <table class="totals">
                        <tr><td>Subtotal</td><td class="num">{{ QuotePdf::money($invoice->total) }}</td></tr>
                    </table>
                    <table class="total-box" style="margin-top: 4px;">
                        <tr>
                            <td class="k">TOTAL</td>
                            <td class="v">{{ QuotePdf::money($invoice->total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
@endsection

@section('page-fixed')
    <div class="fiscal-wrap">
        <table class="fiscal">
            <tr>
                <td class="qr"><img src="{{ $qr }}" alt="QR de ARCA"></td>
                <td>
                    <div class="badge">Comprobante autorizado por ARCA</div>
                    <div class="badge-sub">Escaneá el código para verificarlo en el sitio de ARCA.</div>
                </td>
                <td class="cae">
                    <table style="width: 100%;">
                        <tr><td class="k">CAE N.º</td><td class="v">{{ $invoice->cae }}</td></tr>
                        <tr><td class="k">Vto. del CAE</td><td class="v">{{ $invoice->cae_due_on?->format('d/m/Y') }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
@endsection
