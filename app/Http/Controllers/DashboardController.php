<?php
namespace App\Http\Controllers;
use App\Models\{Borrower, Loan, Payment};
use App\Services\LoanCalculator;
class DashboardController extends Controller
{
    public function __invoke(LoanCalculator $calculator)
    {
        $counts = array_fill_keys(['ACTIVE', 'DUE_SOON', 'OVERDUE', 'FULLY_PAID'], 0);
        $totals = ['principal' => 0, 'balance' => 0, 'interest' => 0];
        Loan::with('ledgerEntries')->chunkById(200, function ($loans) use ($calculator, &$counts, &$totals) {
            foreach ($loans as $loan) {
                $summary = $calculator->summary($loan);
                $counts[$summary['status']]++;
                $totals['principal'] += $summary['principal_amount'];
                $totals['balance'] += $summary['balance'];
                $totals['interest'] += $summary['interest_accrued'];
            }
        });
        $totals['paid'] = Payment::where('status', 'POSTED')->sum('amount');
        return view('dashboard', ['counts' => $counts, 'totals' => $totals, 'borrowerCount' => Borrower::count()]);
    }
}
