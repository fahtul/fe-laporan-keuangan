@extends('finance.report-layout')

@section('title', 'Dashboard Keuangan')
@section('subtitle', 'Ringkasan chart (Income Statement, Neraca, Ekuitas, Arus Kas)')

@section('header_actions')
    <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
        href="{{ route('finance.financial_dashboard.index') }}">
        Reset
    </a>
@endsection

@section('header_meta')
    <span class="report-chip">
        Periode: {{ (string) data_get($period, 'from_date', $fromDate) }} s/d {{ (string) data_get($period, 'to_date', $toDate) }}
    </span>
    <span class="report-chip">
        Interval: {{ $interval }}
    </span>
    <span class="report-chip">
        Chart: {{ $chartType }}
    </span>
@endsection

@section('tools')
    <form class="grid md:grid-cols-5 gap-3 items-end" method="GET" action="{{ route('finance.financial_dashboard.index') }}">
        <div>
            <label class="block text-xs mb-1 text-gray-600">From</label>
            <input type="date" name="from_date" value="{{ $fromDate }}"
                class="border rounded px-3 py-2 w-full">
        </div>
        <div>
            <label class="block text-xs mb-1 text-gray-600">To</label>
            <input type="date" name="to_date" value="{{ $toDate }}"
                class="border rounded px-3 py-2 w-full">
        </div>
        <div>
            <label class="block text-xs mb-1 text-gray-600">Interval</label>
            <select name="interval" class="border rounded px-3 py-2 w-full">
                @foreach ($allowedIntervals as $opt)
                    <option value="{{ $opt }}" @selected($interval === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs mb-1 text-gray-600">Jenis Chart</label>
            <select name="chart_type" class="border rounded px-3 py-2 w-full">
                @foreach ($allowedChartTypes as $opt)
                    <option value="{{ $opt }}" @selected($chartType === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button class="px-4 py-2 rounded bg-indigo-600 text-white hover:bg-indigo-700">Terapkan</button>
            <a class="px-4 py-2 rounded border bg-white text-gray-900 hover:bg-gray-50"
                href="{{ route('finance.financial_dashboard.index') }}">Reset</a>
        </div>
    </form>
@endsection

@section('content')
    @if ($apiError)
        <div class="p-3 rounded bg-red-100 text-red-800 border">
            {{ $apiError }}
        </div>
    @endif

    @php
        $p = is_array($payload ?? null) ? $payload : [];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border rounded p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="font-semibold text-gray-900">Laba Rugi</div>
                    <div class="text-sm text-gray-600">Pendapatan vs Beban vs Laba Bersih</div>
                </div>
            </div>
            <div class="mt-4">
                <canvas id="chartIncome"></canvas>
                <div id="emptyIncome" class="hidden p-4 text-sm text-gray-600 bg-gray-50 border rounded mt-3">No data</div>
            </div>
        </div>

        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Neraca</div>
            <div class="text-sm text-gray-600">Total Aset, Kewajiban, Ekuitas</div>
            <div class="mt-4">
                <canvas id="chartBalance"></canvas>
                <div id="emptyBalance" class="hidden p-4 text-sm text-gray-600 bg-gray-50 border rounded mt-3">No data</div>
            </div>
        </div>

        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Ekuitas</div>
            <div class="text-sm text-gray-600">Closing equity (signed) & Net profit</div>
            <div class="mt-4">
                <canvas id="chartEquity"></canvas>
                <div id="emptyEquity" class="hidden p-4 text-sm text-gray-600 bg-gray-50 border rounded mt-3">No data</div>
            </div>
        </div>

        <div class="bg-white border rounded p-4">
            <div class="font-semibold text-gray-900">Arus Kas</div>
            <div class="text-sm text-gray-600">Operating / Investing / Financing + Net change</div>
            <div class="mt-4">
                <canvas id="chartCashflow"></canvas>
                <div id="emptyCashflow" class="hidden p-4 text-sm text-gray-600 bg-gray-50 border rounded mt-3">No data</div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const payload = @json($p);
            const chartType = @json($chartType);
            const charts = {};

            function toNumberArray(arr) {
                if (!Array.isArray(arr)) return [];
                return arr.map(v => {
                    const n = Number(v);
                    return Number.isFinite(n) ? n : 0;
                });
            }

            function fmtMoney(v) {
                const n = Number(v || 0);
                return n.toLocaleString('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0
                });
            }

            function showEmpty(id, show) {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.toggle('hidden', !show);
            }

            function getLastValue(arr) {
                if (!Array.isArray(arr) || arr.length === 0) return 0;
                for (let i = arr.length - 1; i >= 0; i--) {
                    const n = Number(arr[i]);
                    if (Number.isFinite(n)) return n;
                }
                return 0;
            }

            function doughnutify(datasets) {
                const labels = (datasets || []).map(d => String(d.label || ''));
                const values = (datasets || []).map(d => getLastValue(d.data));
                const colors = [
                    '#2563eb', '#16a34a', '#f59e0b', '#ef4444',
                    '#7c3aed', '#0ea5e9', '#111827',
                ];

                return {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: labels.map((_, i) => colors[i % colors.length]),
                        borderWidth: 1,
                        borderColor: '#ffffff',
                    }]
                };
            }

            function buildChart(canvasId, emptyId, labels, datasets) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const has = Array.isArray(labels) && labels.length > 0;
                showEmpty(emptyId, !has);
                if (!has || !window.Chart) return;

                if (charts[canvasId]) {
                    charts[canvasId].destroy();
                    delete charts[canvasId];
                }

                const baseType =
                    (chartType === 'doughnut') ? 'doughnut'
                    : (chartType === 'bar' || chartType === 'stacked_bar') ? 'bar'
                    : 'line';

                const isStacked = chartType === 'stacked_bar';
                const isDoughnut = chartType === 'doughnut';

                let chartData = {
                    labels,
                    datasets
                };

                if (isDoughnut) {
                    chartData = doughnutify(datasets);
                } else if (isStacked) {
                    chartData = {
                        labels,
                        datasets: (datasets || []).map(ds => {
                            // Keep "Net Change" as a line overlay for readability
                            if (String(ds.label || '') === 'Net Change') {
                                return {
                                    ...ds,
                                    type: 'line',
                                    order: 10,
                                    pointRadius: 2,
                                };
                            }
                            return {
                                ...ds,
                                order: 1,
                            };
                        }),
                    };
                }

                charts[canvasId] = new Chart(canvas, {
                    type: baseType,
                    data: {
                        ...chartData,
                    },
                    options: {
                        responsive: true,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        const val = (typeof ctx.parsed === 'number') ? ctx.parsed : (ctx.parsed?.y ?? 0);
                                        const label = ctx.dataset?.label ? `${ctx.dataset.label}: ` : '';
                                        return `${label}${fmtMoney(val)}`;
                                    }
                                }
                            }
                        },
                        scales: isDoughnut ? undefined : {
                            x: {
                                stacked: isStacked,
                            },
                            y: {
                                stacked: isStacked,
                                ticks: {
                                    callback: (v) => fmtMoney(v)
                                }
                            }
                        },
                    }
                });

                return charts[canvasId];
            }

            const income = payload.income_statement || {};
            buildChart('chartIncome', 'emptyIncome',
                income.labels || [], [
                    {
                        label: 'Total Revenue',
                        data: toNumberArray(income.series?.total_revenue),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,.15)',
                        tension: 0.25
                    },
                    {
                        label: 'Total Operating Expense',
                        data: toNumberArray(income.series?.total_operating_expense),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Net Profit After Tax',
                        data: toNumberArray(income.series?.net_profit_after_tax),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.12)',
                        tension: 0.25
                    }
                ]);

            const balance = payload.balance_sheet || {};
            buildChart('chartBalance', 'emptyBalance',
                balance.labels || [], [
                    {
                        label: 'Assets',
                        data: toNumberArray(balance.series?.assets_total),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Liabilities',
                        data: toNumberArray(balance.series?.liabilities_total),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Equity',
                        data: toNumberArray(balance.series?.equity_total),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,.12)',
                        tension: 0.25
                    }
                ]);

            const equity = payload.equity_statement || {};
            buildChart('chartEquity', 'emptyEquity',
                equity.labels || [], [
                    {
                        label: 'Closing Equity (signed)',
                        data: toNumberArray(equity.series?.closing_equity_signed),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Net Profit',
                        data: toNumberArray(equity.series?.net_profit),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.12)',
                        tension: 0.25
                    }
                ]);

            const cash = payload.cash_flow || {};
            buildChart('chartCashflow', 'emptyCashflow',
                cash.labels || [], [
                    {
                        label: 'Operating',
                        data: toNumberArray(cash.series?.net_cash_from_operating),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Investing',
                        data: toNumberArray(cash.series?.net_cash_from_investing),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Financing',
                        data: toNumberArray(cash.series?.net_cash_from_financing),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,.12)',
                        tension: 0.25
                    },
                    {
                        label: 'Net Change',
                        data: toNumberArray(cash.series?.net_change),
                        borderColor: '#111827',
                        backgroundColor: 'rgba(17,24,39,.10)',
                        borderDash: [6, 6],
                        tension: 0.25
                    }
                ]);
        });
    </script>
@endsection
