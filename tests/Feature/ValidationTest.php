<?php
namespace Tests\Feature;
use App\Models\{Admin, Borrower, Loan, Payment, LedgerEntry};
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Tests\TestCase;
class ValidationTest extends TestCase
{
    use RefreshDatabase;
    private Borrower $borrower;
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(Admin::create(['username'=>'validation_admin', 'password_hash'=>Hash::make('Test-password-123!')]));
        $this->borrower = Borrower::create(['name'=>'Test Person']);
    }
    private function loanData(array $overrides = []): array
    {
        return array_replace(['submission_key'=>(string) Str::uuid(), 'borrower_id'=>$this->borrower->id, 'principal'=>'1000.00', 'date_borrowed'=>today()->toDateString()], $overrides);
    }
    private function loan(): Loan
    {
        return app(LoanService::class)->createLoan($this->borrower->id, '1000', today()->toDateString(), auth()->id());
    }
    private function paymentData(Loan $loan, array $overrides = []): array
    {
        return array_replace(['submission_key'=>(string) Str::uuid(), 'borrower_id'=>$this->borrower->id, 'loan_id'=>$loan->id, 'payment_amount'=>'100', 'payment_date'=>today()->toDateString()], $overrides);
    }
    public function test_money_formats_and_capacity_are_enforced_without_writes(): void
    {
        foreach (['0', '-1', '1.001', '1e3', '1,000', '$10', '9999999999.99', '10000000000', ['100'], 1.25] as $value) {
            $this->postJson('/loans', $this->loanData(['principal'=>$value]))->assertUnprocessable()->assertJsonValidationErrors('principal');
        }
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->postJson('/loans', $this->loanData(['principal'=>'0.01']))->assertRedirect();
        $this->postJson('/loans', $this->loanData(['principal'=>'9090909090.90']))->assertRedirect();
    }
    public function test_dates_ids_and_protected_fields_are_validated(): void
    {
        foreach (['2026-02-30', '01/01/2026', '1899-12-31', today()->addDay()->toDateString(), ['date']] as $date) {
            $this->postJson('/loans', $this->loanData(['date_borrowed'=>$date]))->assertUnprocessable()->assertJsonValidationErrors('date_borrowed');
        }
        foreach ([0, -1, 99999, ['id']] as $id) $this->postJson('/loans', $this->loanData(['borrower_id'=>$id]))->assertUnprocessable()->assertJsonValidationErrors('borrower_id');
        foreach (['admin_id'=>99, 'interest_rate'=>1, 'balance'=>1, 'status'=>'PAID'] as $field=>$value) $this->postJson('/loans', $this->loanData([$field=>$value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('loans', 0);
    }
    public function test_borrower_text_phone_and_normalization(): void
    {
        foreach (['', '   ', '<script>alert(1)</script>', "Bad\u{200B}Name", '123', ['name']] as $name) {
            $this->postJson('/borrowers', ['submission_key'=>(string) Str::uuid(), 'name'=>$name])->assertUnprocessable()->assertJsonValidationErrors('name');
        }
        foreach (['123', '00000000000', '0917abc4567', '++639171234567', '(09171234567', '1234567890123456'] as $phone) {
            $this->postJson('/borrowers', ['submission_key'=>(string) Str::uuid(), 'name'=>'Test Person', 'contact_number'=>$phone])->assertUnprocessable()->assertJsonValidationErrors('contact_number');
        }
        $data = ['submission_key'=>(string) Str::uuid(), 'name'=>"  Jos\u{00E9}  Dela Cruz  ", 'contact_number'=>'+63 (917) 123-4567'];
        $this->postJson('/borrowers', $data)->assertRedirect();
        $this->postJson('/borrowers', $data)->assertRedirect();
        $this->assertDatabaseCount('borrowers', 2);
        $this->assertDatabaseHas('borrowers', ['name'=>"Jos\u{00E9} Dela Cruz", 'contact_number'=>'+639171234567']);
    }
    public function test_payment_ownership_dates_overpayment_and_paid_loans(): void
    {
        $loan = $this->loan(); $other = Borrower::create(['name'=>'Other Person']);
        foreach ([['borrower_id'=>$other->id, 'field'=>'loan_id'], ['payment_date'=>today()->subDay()->toDateString(), 'field'=>'payment_date'], ['payment_amount'=>'1100.01', 'field'=>'payment_amount']] as $invalid) {
            $field = $invalid['field']; unset($invalid['field']);
            $this->postJson('/payments', $this->paymentData($loan, $invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('payments', 0);
        $this->postJson('/payments', $this->paymentData($loan, ['payment_amount'=>'1100']))->assertRedirect();
        $this->postJson('/payments', $this->paymentData($loan))->assertUnprocessable()->assertJsonValidationErrors('payment_amount');
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_duplicate_loan_payment_and_void_submissions_are_idempotent(): void
    {
        $data = $this->loanData();
        $this->postJson('/loans', $data)->assertRedirect(); $this->postJson('/loans', $data)->assertRedirect();
        $this->assertDatabaseCount('loans', 1);
        $loan = Loan::firstOrFail(); $paymentData = $this->paymentData($loan);
        $this->postJson('/payments', $paymentData)->assertRedirect(); $this->postJson('/payments', $paymentData)->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $payment = Payment::firstOrFail(); $void = ['submission_key'=>(string) Str::uuid(), 'reason'=>'Duplicate collection'];
        $this->postJson('/payments/'.$payment->id.'/void', $void)->assertRedirect(); $this->postJson('/payments/'.$payment->id.'/void', $void)->assertRedirect();
        $this->assertSame(1, LedgerEntry::where('type', 'PAYMENT_REVERSAL')->count());
        $this->postJson('/loans', array_replace($data, ['principal'=>'2000']))->assertUnprocessable()->assertJsonValidationErrors('submission_key');
        session()->flush();
        $this->actingAs(Admin::create(['username'=>'other_admin', 'password_hash'=>Hash::make('Test-password-123!')]));
        $this->postJson('/loans', $data)->assertUnprocessable()->assertJsonValidationErrors('submission_key');
    }
    public function test_failed_payment_can_be_corrected_using_same_form(): void
    {
        $loan = $this->loan(); $data = $this->paymentData($loan, ['payment_amount'=>'1200']);
        $this->postJson('/payments', $data)->assertUnprocessable();
        $this->assertDatabaseCount('submission_receipts', 0);
        $data['payment_amount'] = '100';
        $this->postJson('/payments', $data)->assertRedirect();
        $this->assertDatabaseCount('submission_receipts', 1);
        $this->assertDatabaseCount('payments', 1);
    }
    public function test_corrupt_ledger_cannot_be_reversed(): void
    {
        $loan = $this->loan(); $payment = app(LoanService::class)->recordPayment($loan->id, '100', today()->toDateString(), auth()->id());
        DB::table('ledger_entries')->where('payment_id', $payment->id)->update(['amount'=>'99.00']);
        $this->postJson('/payments/'.$payment->id.'/void', ['submission_key'=>(string) Str::uuid(), 'reason'=>'Correct mistake'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame('POSTED', $payment->fresh()->status);
        $this->assertSame(0, LedgerEntry::where('type', 'PAYMENT_REVERSAL')->count());
        $this->assertDatabaseCount('submission_receipts', 0);
    }
    public function test_missing_submission_key_filters_and_password_reuse(): void
    {
        $data = $this->loanData(); unset($data['submission_key']);
        $this->postJson('/loans', $data)->assertUnprocessable()->assertJsonValidationErrors('submission_key');
        $this->getJson('/loans?status=INVALID')->assertUnprocessable();
        $this->getJson('/loans?page=-1')->assertUnprocessable();
        $this->getJson('/borrowers?page=0')->assertUnprocessable();
        $this->putJson('/settings/password', ['current_password'=>'Test-password-123!', 'password'=>'Test-password-123!', 'password_confirmation'=>'Test-password-123!'])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
