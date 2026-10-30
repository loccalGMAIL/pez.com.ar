@php
    use App\Support\QuotePdf;

    $font = fn (string $file) => 'file://'.resource_path("fonts/lato/{$file}");
    $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('images/pez-logo.png')));
    $client = $quote->client;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Presupuesto {{ $quote->code }}</title>
    <style>
        @font-face { font-family: 'Lato'; font-weight: normal; font-style: normal; src: url('{{ $font('Lato-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Lato'; font-weight: bold; font-style: normal; src: url('{{ $font('Lato-Bold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Lato'; font-weight: normal; font-style: italic; src: url('{{ $font('Lato-Italic.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Lato'; font-weight: bold; font-style: italic; src: url('{{ $font('Lato-BoldItalic.ttf') }}') format('truetype'); }

        /* Page margins keep long quotes clear of the edges and the footer; the header bleeds into the top one. */
        @page { margin: 32px 0 74px 0; }
        body { margin: 0; font-family: 'Lato', sans-serif; font-size: 9.5px; color: #0a2e36; }
        table { border-collapse: collapse; }

        .header { background: #0a2e36; color: #fff; width: 100%; height: 92px; margin-top: -32px; }
        .header td { vertical-align: middle; padding-top: 18px; padding-bottom: 8px; }
        .logo { width: 74px; margin-left: 62px; }
        .kicker { color: #27fb6b; font-size: 8px; font-weight: bold; letter-spacing: .3px; }
        .doc-title { font-size: 15px; font-weight: bold; margin-top: 3px; }
        .meta { width: 160px; margin-right: 62px; }
        .meta td { padding: 1.5px 0; font-size: 8.5px; }
        .meta .k { color: #9fb3b7; text-align: right; padding-right: 14px; }
        .meta .v { text-align: right; font-weight: bold; font-size: 9.5px; }

        .content { padding: 18px 62px 20px; }
        .section-title { color: #1fa84f; font-weight: bold; font-size: 9px; margin: 0 0 6px; }

        .client { width: 100%; margin-bottom: 18px; }
        .client td { width: 50%; vertical-align: top; padding: 0 30px 6px 0; }
        .field-label { color: #5b6f73; font-size: 8px; }
        .field-value { font-weight: bold; font-size: 10.5px; border-bottom: 1px solid #c9d3d5; padding: 1px 0 4px; min-height: 13px; }

        table.items { width: 100%; }
        table.items th { background: #0a2e36; color: #fff; font-size: 9.5px; font-weight: bold; padding: 7px 16px; text-align: left; }
        table.items th.qty { text-align: center; }
        table.items th.amount { text-align: right; }
        table.items th.first { border-top-left-radius: 7px; }
        table.items th.last { border-top-right-radius: 7px; }
        table.items td { padding: 5px 16px; vertical-align: middle; }
        table.items tr.alt td { background: #f2f5f2; }
        table.items tr.free td { background: #e5ffed; }
        table.items tr.free td.first { border-left: 3px solid #1fa84f; padding-left: 13px; }
        table.items tr.last-row td { border-bottom: 1.5px solid #0a2e36; }
        .item-title { font-weight: bold; font-size: 10.5px; }
        .item-desc { color: #4b5f63; font-size: 9px; }
        .qty { text-align: center; width: 70px; }
        .amount { text-align: right; width: 120px; font-weight: bold; font-size: 10.5px; white-space: nowrap; }
        .free-label { color: #1fa84f; font-weight: bold; }

        .totals { width: 100%; }
        .totals td { padding: 6px 0; color: #5b6f73; }
        .totals .num { text-align: right; }
        .total-box { width: 100%; margin-top: 14px; background: #0a2e36; color: #fff; border-radius: 7px; }
        .total-box td { padding: 13px 16px; }
        .total-box .k { color: #27fb6b; font-weight: bold; font-size: 9px; }
        .total-box .v { text-align: right; font-weight: bold; font-size: 16px; }

        .summary { width: 100%; margin-top: 4px; }
        .summary td.summary-left { vertical-align: top; padding: 10px 24px 0 0; }
        .summary td.summary-right { vertical-align: top; width: 252px; }
        .installments { width: 100%; }
        .installments td { padding: 3px 0; }
        .installments .head { color: #1fa84f; font-weight: bold; font-size: 9px; padding-bottom: 5px; }
        .installments .num { text-align: right; font-weight: bold; font-size: 10.5px; white-space: nowrap; padding-right: 12px; }
        .installments .sub { color: #5b6f73; font-size: 8px; white-space: nowrap; }
        .conditions { width: 100%; margin-top: 4px; }
        .conditions td { padding: 5px 0 3px; vertical-align: top; }
        .bullet { width: 18px; color: #1fa84f; font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
        .cond-k { width: 110px; font-weight: bold; }
        .cond-v { border-bottom: 1px solid #c9d3d5; }
        .footnote { color: #5b6f73; font-style: italic; font-size: 8px; margin-top: 12px; }
        .pre { white-space: pre-line; }

        .footer { position: fixed; bottom: -74px; left: 0; right: 0; height: 62px; background: #0a2e36; color: #fff; }
        .footer table { width: 100%; margin-top: 15px; }
        .footer td { padding: 0 62px; vertical-align: bottom; }
        .slogan { color: #27fb6b; font-style: italic; font-size: 9px; }
        .owner { font-weight: bold; font-size: 9px; margin-top: 5px; }
        .contact { color: #9fb3b7; font-size: 9px; text-align: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 160px;"><img class="logo" src="{{ $logo }}" alt="{{ $company['name'] }}"></td>
            <td>
                <div class="kicker">PRESUPUESTO</div>
                <div class="doc-title">{{ $quote->title ?: $company['name'] }}</div>
            </td>
            <td style="width: 222px;">
                <table class="meta">
                    <tr><td class="k">N.º</td><td class="v">{{ $quote->code }}</td></tr>
                    <tr><td class="k">FECHA</td><td class="v">{{ $quote->issue_date->format('d/m/Y') }}</td></tr>
                    <tr><td class="k">VALIDEZ</td><td class="v">{{ $quote->validDays() }} {{ $quote->validDays() === 1 ? 'día' : 'días' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="content">
        <div class="section-title">CLIENTE</div>
        <table class="client">
            <tr>
                <td>
                    <div class="field-label">Nombre / Razón social</div>
                    <div class="field-value">{{ $client->business_name }}</div>
                </td>
                <td>
                    <div class="field-label">CUIT / DNI</div>
                    <div class="field-value">{{ $client->cuit }}&nbsp;</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="field-label">Teléfono</div>
                    <div class="field-value">{{ $client->phone ?: $client->primaryContact?->phone }}&nbsp;</div>
                </td>
                <td>
                    <div class="field-label">Email</div>
                    <div class="field-value">{{ $client->email ?: $client->primaryContact?->email }}&nbsp;</div>
                </td>
            </tr>
        </table>

        <div class="section-title">DETALLE</div>
        <table class="items">
            <thead>
                <tr>
                    <th class="first">Componente</th>
                    <th class="qty">Cant.</th>
                    <th class="amount last">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quote->items as $item)
                    <tr class="{{ $item->isFree() ? 'free' : ($loop->odd ? '' : 'alt') }} {{ $loop->last ? 'last-row' : '' }}">
                        <td class="first">
                            @if ($item->title)
                                <div class="item-title">{{ $item->title }}</div>
                                <div class="item-desc pre">{{ $item->description }}</div>
                            @else
                                <div class="item-title pre">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td class="qty">{{ $item->isFree() ? '' : trim(QuotePdf::quantity($item->quantity).' '.$item->unit) }}</td>
                        <td class="amount">
                            @if ($item->isFree())
                                <span class="free-label">SIN CARGO</span>
                            @else
                                {{ QuotePdf::money($item->subtotal) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php($installments = $quote->installments())
        <table class="summary">
            <tr>
                <td class="summary-left">
                    @if ($installments->isNotEmpty())
                        <table class="installments">
                            <tr><td class="head" colspan="3">EN CUOTAS</td></tr>
                            @foreach ($installments as $plan)
                                <tr>
                                    <td>{{ $plan['count'] }} cuotas de</td>
                                    <td class="num">{{ QuotePdf::money($plan['amount']) }}</td>
                                    <td class="sub">{{ $plan['surcharge'] > 0 ? 'total '.QuotePdf::money($plan['total']) : 'sin recargo' }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
                <td class="summary-right">
                    <table class="totals">
                        <tr>
                            <td>Subtotal</td>
                            <td class="num">{{ QuotePdf::money($quote->subtotal) }}</td>
                        </tr>
                        @if ($quote->discount > 0)
                            <tr>
                                <td>Descuento</td>
                                <td class="num">- {{ QuotePdf::money($quote->discount) }}</td>
                            </tr>
                        @endif
                    </table>

                    <table class="total-box">
                        <tr>
                            <td class="k">TOTAL</td>
                            <td class="v">{{ QuotePdf::money($quote->total) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="section-title" style="margin-top: 26px;">CONDICIONES</div>
        <table class="conditions">
            @foreach (['Garantía' => $quote->warranty, 'Forma de pago' => $quote->payment_terms, 'Plazo de entrega' => $quote->delivery_time, 'Observaciones' => $quote->notes] as $label => $value)
                <tr>
                    <td class="bullet">&#9679;</td>
                    <td class="cond-k">{{ $label }}</td>
                    <td class="cond-v pre">{{ $value }}&nbsp;</td>
                </tr>
            @endforeach
        </table>

        @if ($quote->terms)
            <div class="footnote pre">{{ $quote->terms }}</div>
        @endif
    </div>

    <div class="footer">
        <table>
            <tr>
                <td>
                    @if ($company['slogan'])
                        <div class="slogan">“{{ $company['slogan'] }}”</div>
                    @endif
                    <div class="owner">{{ mb_strtoupper($company['owner'] ?: $company['name']) }}</div>
                </td>
                <td class="contact">
                    {!! collect([$company['phone'], $company['email'], $company['website']])->filter()->map(fn ($v) => e($v))->join('&nbsp;&nbsp;|&nbsp;&nbsp;') !!}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
