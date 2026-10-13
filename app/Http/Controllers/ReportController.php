<?php

namespace App\Http\Controllers;

use App\Services\{ReportService, LoanCalculator};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(ReportService::TYPES))],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['bail', 'nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'to' => ['bail', 'nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today'],
            'format' => ['nullable', 'string', 'in:csv,print'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $to = $data['to'] ?? today()->toDateString();
        $filters = ['type' => $data['type'] ?? 'collections', 'search' => trim($data['search'] ?? ''),
            'from' => $data['from'] ?? substr($to, 0, 7).'-01', 'to' => $to];
        $snapshot = in_array($filters['type'], ['outstanding', 'due'], true);
        if ($filters['from'] > $filters['to']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['from' => 'The from date must be on or before the to date.']);
        }
        $report = $reports->build($filters);
        $types = ReportService::TYPES;
        $generatedAt = now()->format('Y-m-d H:i:s T');
        $preparedBy = $request->user()->username.' ('.$request->user()->reference.')';
        $reportCode = 'RPT-'.strtoupper($filters['type']);
        if (($data['format'] ?? '') === 'csv') {
            return response()->streamDownload(function () use ($report, $filters, $snapshot, $types, $generatedAt, $preparedBy, $reportCode) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                $write = function (array $cells) use ($output) {
                    // Keep user-entered borrower names as text in spreadsheet applications.
                    $cells = array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+@-]/u', $v) ? "'".$v : $v, $cells);
                    fputcsv($output, $cells, ',', '"', '');
                };
                $write(['Lending System', $types[$filters['type']]]);
                $write(['Report code', $reportCode, 'Currency', 'PHP']);
                $write(['Generated', $generatedAt, 'Prepared by', $preparedBy]);
                $write([$snapshot ? 'As of' : 'From', $snapshot ? $filters['to'] : $filters['from'], 'To', $filters['to']]);
                $write(['Borrower filter', $filters['search'] ?: 'All borrowers']);
                $write(['Records', $report['rows']->count()]);
                $write([$snapshot ? 'Due date' : 'Date', 'Borrower ID', 'Borrower', 'Loan ID', 'Transaction ID', 'Payment ID', 'Details', 'Amount (PHP)']);
                foreach ($report['rows'] as $row) {
                    // Numeric amounts are generated internally, not user text.
                    $name = preg_match('/^[\s]*[=+@-]/u', $row['borrower']) ? "'".$row['borrower'] : $row['borrower'];
                    fputcsv($output, [$row['date'], $row['borrower_reference'], $name, $row['loan_reference'], $row['transaction_reference'], $row['payment_reference'], $row['description'], LoanCalculator::decimal($row['amount'])], ',', '"', '');
                }
                fputcsv($output, ['', '', '', '', '', '', 'Total (PHP)', LoanCalculator::decimal($report['reportTotal'])], ',', '"', '');
                fclose($output);
            }, 'lending-'.$filters['type'].'-'.$filters['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
        $printing = ($data['format'] ?? '') === 'print';
        $page = (int) ($data['page'] ?? 1);
        $rows = $printing ? $report['rows'] : new LengthAwarePaginator($report['rows']->forPage($page, 20), $report['rows']->count(), 20, $page, ['path' => $request->url(), 'query' => $filters]);
        return response()->view('reports.index', compact('report', 'filters', 'types', 'snapshot', 'printing', 'rows', 'generatedAt', 'preparedBy', 'reportCode'))->header('Cache-Control', 'no-store');
    }
}
