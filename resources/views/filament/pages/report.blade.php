{{--
    Screen of a report (App\Filament\Pages\Reports\ReportPage). Filament's CSS has
    no Tailwind utilities beyond its own, so the layout is styled here with the
    panel's color variables, which already switch for dark mode.
--}}
@php
    $report = $this->report();
@endphp

<x-filament-panels::page>
    <style>
        .pez-report-intro { color: var(--gray-500); font-size: .875rem; }
        .pez-report-intro strong { color: var(--gray-950); }
        .dark .pez-report-intro strong { color: #fff; }
        .pez-report-metrics { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
        .pez-report-metric { background: #fff; border-radius: .75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgb(0 0 0 / .05); outline: 1px solid rgb(0 0 0 / .05); }
        .dark .pez-report-metric { background: var(--gray-900); outline-color: rgb(255 255 255 / .1); }
        .pez-report-metric-label { color: var(--gray-500); font-size: .8125rem; font-weight: 500; }
        .pez-report-metric-value { color: var(--gray-950); font-size: 1.5rem; font-weight: 600; line-height: 2rem; margin-top: .25rem; overflow-wrap: anywhere; }
        .dark .pez-report-metric-value { color: #fff; }
        .pez-report-metric-hint { color: var(--gray-500); font-size: .75rem; margin-top: .25rem; }
        .pez-report-scroll { overflow-x: auto; margin: -.25rem 0; }
        .pez-report-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .pez-report-table th { color: var(--gray-500); font-weight: 600; text-align: left; padding: .5rem .75rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .pez-report-table td { color: var(--gray-950); padding: .5rem .75rem; border-bottom: 1px solid var(--gray-100); white-space: nowrap; }
        .pez-report-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .pez-report-table tfoot td { font-weight: 600; border-top: 2px solid var(--gray-300); border-bottom: 0; }
        .dark .pez-report-table th { border-bottom-color: rgb(255 255 255 / .1); }
        .dark .pez-report-table td { color: #fff; border-bottom-color: rgb(255 255 255 / .05); }
        .dark .pez-report-table tfoot td { border-top-color: rgb(255 255 255 / .2); }
        .pez-report-empty { color: var(--gray-500); font-size: .875rem; }
    </style>

    {{ $this->form }}

    <p class="pez-report-intro">
        <strong>{{ $report->periodLabel() }}</strong>@if ($report->client) · <strong>{{ $report->client->business_name }}</strong>@endif ·
        {{ $report->description() }}
    </p>

    <div class="pez-report-metrics">
        @foreach ($report->metrics() as $metric)
            <div class="pez-report-metric">
                <div class="pez-report-metric-label">{{ $metric->label }}</div>
                <div class="pez-report-metric-value">{{ $metric->formatted() }}</div>
                @if ($metric->hint)
                    <div class="pez-report-metric-hint">{{ $metric->hint }}</div>
                @endif
            </div>
        @endforeach
    </div>

    @foreach ($report->tables() as $table)
        <x-filament::section :heading="$table->title" :description="$table->description">
            @if ($table->rows === [])
                <p class="pez-report-empty">{{ $table->emptyMessage }}</p>
            @else
                <div class="pez-report-scroll">
                    <table class="pez-report-table">
                        <thead>
                            <tr>
                                @foreach ($table->columns as $column)
                                    <th @class(['num' => $column->format->isNumeric()])>{{ $column->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($table->rows as $row)
                                <tr>
                                    @foreach ($table->columns as $column)
                                        <td @class(['num' => $column->format->isNumeric()])>{{ $column->value($row) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($table->totals)
                            <tfoot>
                                <tr>
                                    @foreach ($table->columns as $column)
                                        <td @class(['num' => $column->format->isNumeric()])>{{ array_key_exists($column->key, $table->totals) ? $column->value($table->totals) : '' }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
