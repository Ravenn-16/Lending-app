<?php
namespace App\Http\Controllers;
use App\Models\Borrower;
use App\Http\Requests\BorrowerRequest;
use App\Services\SubmissionService;
use App\Services\LoanCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class BorrowerController extends Controller
{
    public function index(Request $request, LoanCalculator $calculator)
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000000']])['search'] ?? '';
        $borrowers = Borrower::with('loans.ledgerEntries')->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('location', 'like', '%'.$search.'%')->orWhere('contact_number', 'like', '%'.$search.'%');
            });
        })->latest('id')->paginate(10)->withQueryString();
        foreach ($borrowers as $borrower) $borrower->balance = $borrower->loans->sum(fn ($loan) => $calculator->summary($loan)['balance']);
        return view('borrowers.index', compact('borrowers', 'search'));
    }
    public function create() { return view('borrowers.form', ['borrower' => new Borrower]); }
    public function store(BorrowerRequest $request, SubmissionService $submissions)
    {
        $data = $request->borrowerData();
        $id = $submissions->run($request->validated('submission_key'), $request->user()->id, 'borrower', $data, fn () => Borrower::create($data)->id);
        return redirect()->route('borrowers.show', $id)->with('success', 'Borrower added successfully.');
    }
    public function show(Borrower $borrower, LoanCalculator $calculator)
    {
        $borrower->load('loans.ledgerEntries');
        $summaries = $borrower->loans->mapWithKeys(fn ($loan) => [$loan->id => $calculator->summary($loan)]);
        return view('borrowers.show', compact('borrower', 'summaries'));
    }
    public function edit(Borrower $borrower) { return view('borrowers.form', compact('borrower')); }
    public function update(BorrowerRequest $request, Borrower $borrower)
    {
        $borrower->update($request->borrowerData());
        return redirect()->route('borrowers.show', $borrower)->with('success', 'Borrower updated successfully.');
    }
    public function destroy(Borrower $borrower)
    {
        DB::transaction(function () use ($borrower) {
            $locked = Borrower::query()->lockForUpdate()->findOrFail($borrower->id);
            if ($locked->loans()->exists()) throw ValidationException::withMessages(['borrower' => 'Cannot delete a borrower with existing loans.']);
            $locked->delete();
        });
        return redirect()->route('borrowers.index')->with('success', 'Borrower deleted successfully.');
    }
}
