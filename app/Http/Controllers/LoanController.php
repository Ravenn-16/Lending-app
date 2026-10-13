<?php
namespace App\Http\Controllers;
use App\Models\Loan;
use App\Services\LoanCalculator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
class LoanController extends Controller
{
    public function index(Request $request, LoanCalculator $calculator)
    {
        $data = $request->validate(['status' => ['bail', 'nullable', 'string', 'in:ACTIVE,DUE_SOON,OVERDUE,FULLY_PAID'], 'search' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);
        $status = $data['status'] ?? 'ACTIVE';
        $search = $data['search'] ?? '';
        $rows = collect();
        Loan::with(['borrower', 'ledgerEntries'])->when($search !== '', fn ($query) => $query->whereHas('borrower', fn ($q) => $q->where('name', 'like', '%'.$search.'%')))
            ->chunkById(200, function ($loans) use ($calculator, $status, &$rows) {
                foreach ($loans as $loan) {
                    $summary = $calculator->summary($loan);
                    if ($summary['status'] === $status) $rows->push(['loan' => $loan, 'summary' => $summary]);
                }
            });
        $rows = $rows->sortByDesc(fn ($row) => $row['loan']->date_borrowed)->values();
        $page = (int)($data['page'] ?? 1);
        $loans = new LengthAwarePaginator($rows->forPage($page, 10), $rows->count(), 10, $page, ['path' => $request->url(), 'query' => $request->query()]);
        return view('loans.index', compact('loans', 'status', 'search'));
    }
    public function show(Loan $loan, LoanCalculator $calculator)
    {
        $loan->load(['borrower', 'payments', 'ledgerEntries']);
        $summary = $calculator->summary($loan);
        return view('loans.show', compact('loan', 'summary'));
    }
}
