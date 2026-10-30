@extends('pdf.layout', [
    'kicker' => 'ORDEN DE TRABAJO',
    'heading' => 'Ingreso de equipo',
    'meta' => [
        'N.º' => $order->code,
        'INGRESO' => $order->opened_on->format('d/m/Y'),
    ],
])

@section('title', "Orden de trabajo {$order->code}")

@section('content')
        @include('pdf.partials.client', ['client' => $order->client])

        <div class="section-title">EQUIPOS RECIBIDOS</div>
        <table class="items">
            <thead>
                <tr>
                    <th class="first last">Equipo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->devices as $device)
                    <tr class="{{ $loop->odd ? '' : 'alt' }} {{ $loop->last ? 'last-row' : '' }}">
                        <td class="first">
                            <div class="item-title">{{ $device->label }}</div>
                            @if ($device->notes)
                                <div class="item-desc">{{ $device->notes }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="last-row">
                        <td class="first"><div class="item-desc">Sin equipos cargados</div></td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="section-title">PEDIDO</div>
        <table class="items">
            <tbody>
                <tr class="last-row">
                    <td class="first">
                        <div class="item-title">{{ $order->title }}</div>
                        @if ($order->description)
                            <div class="item-desc">{!! nl2br(e($order->description)) !!}</div>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="item-desc">Conservá este comprobante: se solicita para retirar el equipo.</p>
@endsection
