@php
    use App\Support\QuotePdf;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Presupuesto {{ $quote->code }}</title>
    <style>
        @page { margin: 36px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #000; }
        h1 { font-size: 18px; margin: 0; }
        .muted { color: #444; }
        .header { width: 100%; margin-bottom: 22px; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .box { width: 100%; margin-bottom: 22px; }
        .box td { vertical-align: top; width: 50%; }
        .label { font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { font-weight: bold; font-size: 11px; text-align: center; padding: 4px 6px 10px; vertical-align: bottom; }
        table.items th.desc { text-align: center; }
        table.items td { padding: 2px 6px; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        table.items tr.total td { font-weight: bold; padding-top: 12px; }
        .bar { height: 14px; background: #000; margin-top: 18px; }
        .section { margin-top: 22px; }
        .pre { white-space: pre-line; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $company['name'] }}</h1>
                <div class="muted">
                    @if ($company['cuit']) CUIT {{ $company['cuit'] }}<br>@endif
                    @if ($company['address']) {{ $company['address'] }}<br>@endif
                    {{ collect([$company['email'], $company['phone'], $company['website']])->filter()->join(' · ') }}
                </div>
            </td>
            <td class="right">
                <h1>Presupuesto {{ $quote->code }}</h1>
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
                <th class="desc">Descripción</th>
                <th>Cantidad</th>
                <th>Precio<br>Unitario $</th>
                <th>Importe $</th>
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
            @if ($quote->discount > 0)
                <tr class="total">
                    <td class="right" colspan="3">SUBTOTAL</td>
                    <td class="num">{{ QuotePdf::money($quote->subtotal) }}</td>
                </tr>
                <tr>
                    <td class="right" colspan="3">Descuento</td>
                    <td class="num">- {{ QuotePdf::money($quote->discount) }}</td>
                </tr>
            @endif
            <tr class="total">
                <td class="right" colspan="3">TOTAL</td>
                <td class="num">{{ QuotePdf::money($quote->total) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="bar"></div>

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
