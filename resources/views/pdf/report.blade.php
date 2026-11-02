@extends('pdf.layout', [
    'kicker' => 'INFORME',
    'heading' => $report::title(),
    'meta' => array_filter([
        'PERÍODO' => $report->periodLabel(),
        'CLIENTE' => $report->client?->business_name,
        'EMITIDO' => today()->format('d/m/Y'),
    ]),
])

@section('title', 'Informe de '.mb_strtolower($report::title()).' — '.$report->periodLabel())

@section('styles')
        .report-description { color: #5b6f73; font-size: 9px; margin: 0 0 12px; }
        .metrics { width: 100%; margin-bottom: 16px; }
        .metrics td { width: 33.33%; vertical-align: top; padding: 0 6px 8px 0; }
        .metric { background: #f2f5f2; border-radius: 6px; padding: 8px 10px; }
        .metric-label { color: #5b6f73; font-size: 8px; }
        .metric-value { font-weight: bold; font-size: 13px; margin-top: 2px; }
        .metric-hint { color: #5b6f73; font-size: 7.5px; margin-top: 2px; }
        .report-table { margin-bottom: 16px; page-break-inside: auto; }
        .report-table tr { page-break-inside: avoid; }
        .report-table .table-description { color: #5b6f73; font-size: 8px; margin: -2px 0 6px; }
        table.items.compact th, table.items.compact td { padding: 4px 8px; font-size: 8.5px; }
        table.items.compact td.num, table.items.compact th.num { text-align: right; white-space: nowrap; }
        table.items.compact tr.totals-row td { font-weight: bold; border-top: 1.5px solid #0a2e36; }
        .empty { color: #5b6f73; font-style: italic; padding: 6px 0 10px; }
@endsection

@section('content')
        <p class="report-description">{{ $report->description() }}</p>

        <table class="metrics">
            @foreach (array_chunk($report->metrics(), 3) as $row)
                <tr>
                    @foreach ($row as $metric)
                        <td>
                            <div class="metric">
                                <div class="metric-label">{{ $metric->label }}</div>
                                <div class="metric-value">{{ $metric->formatted() }}</div>
                                @if ($metric->hint)
                                    <div class="metric-hint">{{ $metric->hint }}</div>
                                @endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>

        @foreach ($report->tables() as $table)
            <div class="report-table">
                <div class="section-title">{{ mb_strtoupper($table->title) }}</div>
                @if ($table->description)
                    <div class="table-description">{{ $table->description }}</div>
                @endif

                @if ($table->rows === [])
                    <div class="empty">{{ $table->emptyMessage }}</div>
                @else
                    <table class="items compact">
                        <thead>
                            <tr>
                                @foreach ($table->columns as $column)
                                    <th @class(['num' => $column->format->isNumeric(), 'first' => $loop->first, 'last' => $loop->last])>{{ $column->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($table->rows as $row)
                                <tr @class(['alt' => $loop->even, 'last-row' => $loop->last && ! $table->totals])>
                                    @foreach ($table->columns as $column)
                                        <td @class(['num' => $column->format->isNumeric()])>{{ $column->value($row) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            @if ($table->totals)
                                <tr class="totals-row">
                                    @foreach ($table->columns as $column)
                                        <td @class(['num' => $column->format->isNumeric()])>{{ array_key_exists($column->key, $table->totals) ? $column->value($table->totals) : '' }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
@endsection
