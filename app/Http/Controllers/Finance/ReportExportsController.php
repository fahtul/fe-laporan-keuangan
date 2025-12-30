<?php

namespace App\Http\Controllers\Finance;

use App\Exports\GenericReportExport;
use App\Helpers\FinanceApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportsController extends Controller
{
    public function exportXlsx(Request $request, string $report)
    {
        if (!class_exists(Excel::class)) {
            return response(
                "Excel export is not installed.\n\nRun: composer require maatwebsite/excel\n",
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $registry = $this->registry();

        if (!array_key_exists($report, $registry)) {
            return response(
                "Unknown report key: {$report}\n",
                404,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $entry = $registry[$report];
        $endpoint = (string) $entry['endpoint'];
        $transformerClass = (string) $entry['transformer'];
        $filenamePrefix = (string) $entry['filename_prefix'];

        $params = $request->query();
        
        $res = FinanceApiHelper::get($endpoint, $params);
        if (!($res['success'] ?? false)) {
            return response(
                (string) ($res['message'] ?? 'Failed to fetch report data'),
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $payload = $this->extractPayload($res);

        if (!class_exists($transformerClass)) {
            return response(
                "Missing transformer: {$transformerClass}\n",
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $transformer = app($transformerClass);
        if (!method_exists($transformer, 'transform')) {
            return response(
                "Transformer missing transform(): {$transformerClass}\n",
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $unified = $transformer->transform($payload, $params);

        $filename = $this->buildFilename($filenamePrefix, $unified, $params);

        return Excel::download(new GenericReportExport($unified), $filename);
    }

    private function registry(): array
    {
        return [
            'trial-balance' => [
                'endpoint' => '/v1/trial-balance',
                'transformer' => \App\Reports\ExportTransformers\TrialBalanceExportTransformer::class,
                'filename_prefix' => 'trial-balance',
            ],
            'income-statement' => [
                'endpoint' => '/v1/income-statement',
                'transformer' => \App\Reports\ExportTransformers\IncomeStatementExportTransformer::class,
                'filename_prefix' => 'income-statement',
            ],
            'balance-sheet' => [
                'endpoint' => '/v1/balance-sheet',
                'transformer' => \App\Reports\ExportTransformers\BalanceSheetExportTransformer::class,
                'filename_prefix' => 'balance-sheet',
            ],
            'cash-flow' => [
                'endpoint' => '/v1/cash-flow',
                'transformer' => \App\Reports\ExportTransformers\CashFlowExportTransformer::class,
                'filename_prefix' => 'cash-flow',
            ],
            'ledger' => [
                'endpoint' => '/v1/ledgers',
                'transformer' => \App\Reports\ExportTransformers\LedgerExportTransformer::class,
                'filename_prefix' => 'ledger',
            ],
            'subsidiary-ledger' => [
                'endpoint' => '/v1/subledgers',
                'transformer' => \App\Reports\ExportTransformers\SubsidiaryLedgerExportTransformer::class,
                'filename_prefix' => 'subsidiary-ledger',
            ],
            'equity' => [
                'endpoint' => '/v1/equity-statement',
                'transformer' => \App\Reports\ExportTransformers\EquityExportTransformer::class,
                'filename_prefix' => 'equity-statement',
            ],
            'worksheet' => [
                'endpoint' => '/v1/worksheets',
                'transformer' => \App\Reports\ExportTransformers\WorksheetExportTransformer::class,
                'filename_prefix' => 'worksheet',
            ],
        ];
    }

    private function extractPayload(array $res): mixed
    {
        $json = $res['data'] ?? null;
        $payload = data_get($json, 'data.data.data')
            ?? data_get($json, 'data.data')
            ?? data_get($json, 'data')
            ?? $json;

        return $payload;
    }

    private function buildFilename(string $prefix, array $unified, array $params): string
    {
        $period = data_get($unified, 'period', []);
        $from = (string) data_get($period, 'from_date', '');
        $to = (string) data_get($period, 'to_date', '');
        $asOf = (string) data_get($unified, 'period.as_of', '');
        $year = (string) ($params['year'] ?? '');

        $suffix = '';
        if ($from !== '' && $to !== '') {
            $suffix = "{$from}_to_{$to}";
        } elseif ($asOf !== '') {
            $suffix = "as-of_{$asOf}";
        } elseif ($year !== '') {
            $suffix = "year_{$year}";
        }

        $name = $suffix !== '' ? "{$prefix}_{$suffix}" : $prefix;
        $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name) ?: $prefix;

        return $name . '.xlsx';
    }
}
