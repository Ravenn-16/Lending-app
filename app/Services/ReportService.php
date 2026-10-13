<?php

namespace App\Services;

use App\Models\Loan;

class ReportService
{
    public const TYPES = [
        'collections' => 'Payments received',
        'releases' => 'Money lent',
        'interest' => 'Interest added to loans',
        'outstanding' => 'Money still owed',
        'due' => 'Payments due soon or late',
    ];

    public function build(array $filters): array
    {
        $rows = collect();
        $totals = ['releases' => 0, 'collections' => 0, 'interest' => 0, 'outstanding' => 0];
        $calculator = app(LoanCalculator::class);
        Loan::with(['borrower', 'ledgerEntries'])->where('date_borrowed', '<=', $filters['to'])
            ->when($filters['search'] !== '', fn ($q) => $q->whereHas('borrower', fn ($b) => $b->where('name', 'like', '%'.$filters['search'].'%')))
            ->chunkById(200, function ($loans) use ($filters, $calculator, &$totals, $rows) {
                foreach ($loans as $loan) {
                    $summary = $calculator->summary($loan, $filters['to']);
                    $totals['outstanding'] += max(0, $summary['balance_cents']);
                    foreach ($summary['history'] as $entry) {
                        if ($entry['effective_date'] < $filters['from']) continue;
                        $type = match ($entry['type']) {
                            'PRINCIPAL' => 'releases', 'PAYMENT', 'PAYMENT_REVERSAL' => 'collections',
                            'INTEREST' => 'interest', default => null,
                        };
                        if (!$type) continue;
                        $amount = LoanCalculator::cents($entry['amount']) * ($entry['type'] === 'PAYMENT_REVERSAL' ? -1 : 1);
                        $totals[$type] += $amount;
                        if ($filters['type'] === $type) {
                            $description = match ($entry['type']) {
                                'PRINCIPAL' => 'Money lent', 'PAYMENT' => 'Payment received',
                                'PAYMENT_REVERSAL' => 'Payment canceled',
                                default => !empty($entry['calculated']) ? 'Calculated interest' : 'Recorded interest',
                            };
                            $row = $this->row($loan, $entry['effective_date'], $description, $amount);
                            $row['transaction_reference'] = !empty($entry['calculated']) || empty($entry['id']) ? 'Calculated' : \App\Support\Reference::format('LedgerEntry', (int) $entry['id']);
                            $row['payment_reference'] = !empty($entry['payment_id']) ? \App\Support\Reference::format('Payment', (int) $entry['payment_id']) : '';
                            $rows->push($row);
                        }
                    }
                    $include = $filters['type'] === 'outstanding' && $summary['balance_cents'] > 0;
                    $include = $include || ($filters['type'] === 'due' && in_array($summary['status'], ['DUE_SOON', 'OVERDUE'], true));
                    if ($include) {
                        $rows->push($this->row($loan, $summary['next_due_date'], str_replace('_', ' ', $summary['status']), $summary['balance_cents']));
                    }
                }
            });
        $rows = in_array($filters['type'], ['outstanding', 'due'], true)
            ? $rows->sortBy([['date', 'asc'], ['loan_id', 'asc']])->values()
            : $rows->sortBy([['date', 'desc'], ['loan_id', 'desc']])->values();
        return ['rows' => $rows, 'totals' => $totals, 'reportTotal' => $rows->sum('amount')];
    }

    private function row(Loan $loan, string $date, string $description, int $amount): array
    {
        return ['date' => $date, 'borrower' => $loan->borrower?->name ?? 'Borrower unavailable',
            'borrower_reference' => $loan->borrower?->reference ?? '', 'loan_reference' => $loan->reference,
            'transaction_reference' => '', 'payment_reference' => '',
            'loan_id' => $loan->id, 'description' => $description, 'amount' => $amount];
    }
}
