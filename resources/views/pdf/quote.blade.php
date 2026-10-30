@php
    use App\Support\QuotePdf;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Presupuesto {{ $quote->code }}</title>
    <style>
        @page { margin: 32px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #6b7280; }
        .accent { color: #047857; }
        .header { width: 100%; border-bottom: 3px solid #10b981; padding-bottom: 12px; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .box { width: 100%; margin-bottom: 18px; }
        .box td { vertical-align: top; width: 50%; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #ecfdf5; color: #065f46; font-size: 10px; text-align: left; padding: 6px 8px; border-bottom: 1px solid #a7f3d0; }
        table.items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        table.totals { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; }
        table.totals .total td { font-size: 14px; font-weight: bold; border-top: 2px solid #10b981; padding-top: 8px; }
        .section { margin-top: 22px; }
        .pre { white-space: pre-line; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1 class="accent">{{ $company['name'] }}</h1>
                <div class="muted">
                    @if ($company['cuit']) CUIT {{ $company['cuit'] }}<br>@endif
                    @if ($company['address']) {{ $company['address'] }}<br>@endif
                    {{ collect([$company['email'], $company['phone'], $company['website']])->filter()->join(' · ') }}
                </div>
            </td>
            <td class="right">
                <div class="label">Presupuesto</div>
                <h1>{{ $quote->code }}</h1>
                <div>Fecha: {{ $quote->issue_date->format('d/m/Y') }}</div>
                <div>Válido hasta: {{ $quote->valid_until->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td>
                <div class="label">Cliente</div>
                <strong>{{ $quote->client->business_name }}</strong><br>
                @if ($quote->client->trade_name) {{ $quote->client->trade_name }}<br>@endif
                @if ($quote->client->cuit) CUIT {{ $quote->client->cuit }}<br>@endif
                @if ($quote->client->tax_condition) {{ $quote->client->tax_condition->getLabel() }}<br>@endif
            </td>
            <td>
                @if ($quote->client->address || $quote->client->email || $quote->client->phone)
                    <div class="label">Contacto</div>
                    @if ($quote->client->address) {{ collect([$quote->client->address, $quote->client->city])->filter()->join(', ') }}<br>@endif
                    @if ($quote->client->email) {{ $quote->client->email }}<br>@endif
                    @if ($quote->client->phone) {{ $quote->client->phone }}@endif
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Descripción</th>
                <th class="num">Cantidad</th>
                <th class="num">Precio unitario</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td class="pre">{{ $item->description }}</td>
                    <td class="num">{{ QuotePdf::quantity($item->quantity) }} {{ $item->unit }}</td>
                    <td class="num">{{ QuotePdf::money($item->unit_price) }}</td>
                    <td class="num">{{ QuotePdf::money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($quote->discount > 0)
            <tr>
                <td>Subtotal</td>
                <td class="num right">{{ QuotePdf::money($quote->subtotal) }}</td>
            </tr>
            <tr>
                <td>Descuento</td>
                <td class="num right">- {{ QuotePdf::money($quote->discount) }}</td>
            </tr>
        @endif
        <tr class="total">
            <td>Total</td>
            <td class="num right">{{ QuotePdf::money($quote->total) }}</td>
        </tr>
    </table>

    @if ($quote->notes)
        <div class="section">
            <div class="label">Observaciones</div>
            <div class="pre">{{ $quote->notes }}</div>
        </div>
    @endif

    @if ($quote->terms)
        <div class="section">
            <div class="label">Condiciones</div>
            <div class="pre muted">{{ $quote->terms }}</div>
        </div>
    @endif
</body>
</html>
