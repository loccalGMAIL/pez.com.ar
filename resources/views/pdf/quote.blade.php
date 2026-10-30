@extends('pdf.layout', [
    'kicker' => 'PRESUPUESTO',
    'heading' => $quote->title ?: $company['name'],
    'meta' => [
        'N.º' => $quote->code,
        'FECHA' => $quote->issue_date->format('d/m/Y'),
        'VALIDEZ' => $quote->validDays().' '.($quote->validDays() === 1 ? 'día' : 'días'),
    ],
])

@php
    use App\Support\QuotePdf;
@endphp

@section('title', "Presupuesto {$quote->code}")

@section('content')
        @include('pdf.partials.client', ['client' => $quote->client])

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
@endsection
