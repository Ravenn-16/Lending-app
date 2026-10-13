<?php
namespace App\Http\Controllers;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $types = ['PRINCIPAL'=>'Money lent', 'PAYMENT'=>'Payment', 'INTEREST'=>'Interest', 'PAYMENT_REVERSAL'=>'Payment canceled', 'ADJUSTMENT'=>'Balance correction'];
        $filters = $request->validate([
            'search'=>['nullable','string','max:255'],
            'type'=>['bail','nullable','string','in:'.implode(',', array_keys($types))],
            'from'=>['bail','nullable','string','date_format:Y-m-d','after_or_equal:1900-01-01','before_or_equal:today'],
            'to'=>array_merge(['bail','nullable','string','date_format:Y-m-d','after_or_equal:1900-01-01','before_or_equal:today'], $request->filled('from') ? ['after_or_equal:from'] : []),
            'page'=>['bail','nullable','integer','min:1','max:1000000'],
        ]);
        $transactions = LedgerEntry::with(['loan.borrower','payment'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('loan.borrower', fn ($borrowers) => $borrowers->where('name','like','%'.$search.'%')))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->where('effective_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->where('effective_date', '<=', $date))
            ->orderByDesc('effective_date')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('transactions.index', compact('transactions','types','filters'));
    }
}
