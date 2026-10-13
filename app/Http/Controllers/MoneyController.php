<?php
namespace App\Http\Controllers;
use App\Models\{Borrower, Loan, Payment};
use App\Services\{LoanCalculator, LoanService, SubmissionService};
use App\Http\Requests\{StoreLoanRequest, StorePaymentRequest, VoidPaymentRequest};
use App\Exceptions\LendingValidationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class MoneyController extends Controller
{
    public function create(Request $request)
    {
        $request->validate(['loan_id' => ['bail', 'nullable', 'integer', 'min:1', 'exists:loans,id'], 'borrower_id' => ['bail', 'nullable', 'integer', 'min:1', 'exists:borrowers,id'], 'mode' => ['nullable', 'in:payment,new_loan']]);
        $selectedLoan = $request->filled('loan_id') ? Loan::findOrFail($request->integer('loan_id')) : null;
        if ($selectedLoan && $request->filled('borrower_id') && $selectedLoan->borrower_id !== $request->integer('borrower_id')) {
            throw ValidationException::withMessages(['loan_id' => 'Choose a loan belonging to the selected borrower.']);
        }
        $borrowerId = old('borrower_id', $selectedLoan?->borrower_id ?? $request->integer('borrower_id'));
        $mode = old('mode', $selectedLoan ? 'payment' : $request->input('mode', 'new_loan'));
        $mode = in_array($mode, ['new_loan', 'payment'], true) ? $mode : 'new_loan';
        return view('money.create', ['borrowers' => Borrower::orderBy('name')->get(), 'selectedLoan' => $selectedLoan, 'borrowerId' => $borrowerId, 'mode' => $mode]);
    }
    private function run(string $field, callable $action)
    {
        try { return $action(); }
        catch (\InvalidArgumentException | \OverflowException $e) { throw ValidationException::withMessages([($e instanceof LendingValidationException ? $e->field : $field) => $e->getMessage()]); }
    }
    public function loan(StoreLoanRequest $request, LoanService $service, SubmissionService $submissions)
    {
        $data = $request->safe()->only(['borrower_id', 'principal', 'date_borrowed']);
        $id = $this->run('principal', fn () => $submissions->run($request->validated('submission_key'), $request->user()->id, 'loan', $data,
            fn () => $service->createLoan($data['borrower_id'], $data['principal'], $data['date_borrowed'], $request->user()->id)->id));
        return redirect()->route('loans.show', $id)->with('success', 'Loan created with 10% initial interest.');
    }
    public function payment(StorePaymentRequest $request, LoanService $service, SubmissionService $submissions)
    {
        $data = $request->safe()->only(['borrower_id', 'loan_id', 'payment_amount', 'payment_date']);
        $this->run('payment_amount', fn () => $submissions->run($request->validated('submission_key'), $request->user()->id, 'payment', $data,
            fn () => $service->recordPayment($data['loan_id'], $data['payment_amount'], $data['payment_date'], $request->user()->id, $data['borrower_id'])->id));
        return redirect()->route('loans.show', $data['loan_id'])->with('success', 'Payment recorded successfully.');
    }
    public function confirmVoid(Payment $payment, LoanCalculator $calculator)
    {
        $payment->load('loan.borrower');
        return view('money.void', ['payment' => $payment, 'summary' => $calculator->summary($payment->loan)]);
    }
    public function void(VoidPaymentRequest $request, Payment $payment, LoanService $service, SubmissionService $submissions)
    {
        $data = ['payment_id' => $payment->id, 'reason' => $request->validated('reason')];
        $this->run('reason', fn () => $submissions->run($request->validated('submission_key'), $request->user()->id, 'void', $data,
            fn () => $service->voidPayment($payment->id, $data['reason'], $request->user()->id)->id));
        return redirect()->route('loans.show', $payment->loan_id)->with('success', 'Payment canceled. The amount has been added back to the loan.');
    }
}
