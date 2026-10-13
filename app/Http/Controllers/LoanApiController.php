<?php
namespace App\Http\Controllers;
use App\Models\{Borrower, Loan};
use App\Services\LoanCalculator;
use Illuminate\Http\Request;
class LoanApiController extends Controller
{
    public function loans(Borrower $borrower, LoanCalculator $calculator)
    {
        return response()->json($borrower->loans()->with('ledgerEntries')->latest('date_borrowed')->get()->map(function ($loan) use ($calculator) {
            $summary = $calculator->summary($loan);
            return ['id' => $loan->id, 'text' => $loan->reference.' | Borrowed: '.$loan->date_borrowed.', Balance: '."\u{20B1}".number_format($summary['balance'], 2).', Status: '.str_replace('_', ' ', $summary['status'])];
        }));
    }
    public function info(Request $request, Loan $loan, LoanCalculator $calculator)
    {
        $date = $request->validate(['payment_date' => ['bail', 'nullable', 'string', 'date_format:Y-m-d', 'after_or_equal:'.$loan->date_borrowed, 'before_or_equal:today']])['payment_date'] ?? today()->toDateString();
        $summary = $calculator->summary($loan, $date);
        $quote = $calculator->summary($loan, $date, true);
        return response()->json(['id' => $loan->id, 'balance' => $summary['balance'], 'balance_display' => "\u{20B1}".number_format($summary['balance'], 2),
            'payoff_amount' => max(0, $quote['balance']), 'status' => str_replace('_', ' ', $summary['status']), 'next_due_date' => $summary['next_due_date'], 'date_borrowed' => $loan->date_borrowed]);
    }
}
