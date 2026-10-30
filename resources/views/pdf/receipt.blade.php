@extends('pdf.layout', [
    'kicker' => 'RECIBO',
    'heading' => 'Recibo de pago',
    'meta' => [
        'N.º' => $payment->code,
        'FECHA' => $payment->paid_on->format('d/m/Y'),
    ],
])

@php
    use App\Support\AmountInWords;
    use App\Support\QuotePdf;

    $unallocated = $payment->unallocated();
@endphp

@section('title', "Recibo {$payment->code}")

@section('content')
        @include('pdf.partials.client', ['client' => $payment->client])

        <div class="section-title">DETALLE</div>
        <table class="items">
            <thead>
                <tr>
                    <th class="first">Concepto</th>
                    <th class="amount last">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payment->allocations as $allocation)
                    <tr class="{{ $loop->odd ? '' : 'alt' }} {{ $loop->last && $unallocated <= 0 ? 'last-row' : '' }}">
                        <td class="first">
                            <div class="item-title">{{ $allocation->charge->concept }}</div>
                            @if ($allocation->amount < $allocation->charge->amount)
                                <div class="item-desc">Pago parcial de {{ QuotePdf::money($allocation->charge->amount) }}</div>
                            @endif
                        </td>
                        <td class="amount">{{ QuotePdf::money($allocation->amount) }}</td>
                    </tr>
                @endforeach
                @if ($unallocated > 0)
                    <tr class="free last-row">
                        <td class="first">
                            <div class="item-title">A cuenta</div>
                            <div class="item-desc">Queda como saldo a favor para próximos cargos</div>
                        </td>
                        <td class="amount">{{ QuotePdf::money($unallocated) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <table class="summary">
            <tr>
                <td class="summary-left">
                    <table class="conditions">
                        <tr>
                            <td class="bullet">&#9679;</td>
                            <td class="cond-k">Medio de pago</td>
                            <td class="cond-v">{{ $payment->method->getLabel() }}</td>
                        </tr>
                        <tr>
                            <td class="bullet">&#9679;</td>
                            <td class="cond-k">Referencia</td>
                            <td class="cond-v">{{ $payment->reference }}&nbsp;</td>
                        </tr>
                    </table>
                </td>
                <td class="summary-right">
                    <table class="total-box">
                        <tr>
                            <td class="k">TOTAL RECIBIDO</td>
                            <td class="v">{{ QuotePdf::money($payment->amount) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="in-words">Recibimos la suma de <strong>{{ AmountInWords::pesos($payment->amount) }}</strong>.</div>

        @if ($payment->notes)
            <div class="footnote pre">{{ $payment->notes }}</div>
        @endif
@endsection
