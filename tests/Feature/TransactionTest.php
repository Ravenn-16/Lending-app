<?php
namespace Tests\Feature;
use App\Models\{Admin, Borrower, LedgerEntry};
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class TransactionTest extends TestCase
{
    use RefreshDatabase;
    private function signIn(): Admin
    {
        $admin = Admin::create(['username'=>'history_admin','password_hash'=>Hash::make('Test-password-123!')]);
        $this->actingAs($admin);
        return $admin;
    }
    public function test_access_empty_state_and_invalid_filters(): void
    {
        $this->get('/transactions')->assertRedirect('/login');
        $this->signIn();
        $this->get('/transactions')->assertOk()->assertSee('No transactions found.');
        foreach (['type=UNKNOWN','type[]=PAYMENT','from=2026-02-30','from=2026-01-02&to=2026-01-01','page=0','search[]=invalid'] as $query) {
            $this->getJson('/transactions?'.$query)->assertUnprocessable();
        }
    }
    public function test_history_includes_reversals_and_filters_without_mutating_records(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $admin = $this->signIn(); $service = app(LoanService::class);
        $alice = Borrower::create(['name'=>'Alice History']); $bob = Borrower::create(['name'=>'Bob History']);
        $loan = $service->createLoan($alice->id, '1000', '2026-09-01', $admin->id);
        $service->createLoan($bob->id, '500', '2026-09-02', $admin->id);
        $payment = $service->recordPayment($loan->id, '100', '2026-09-03', $admin->id);
        $service->voidPayment($payment->id, 'Duplicate collection', $admin->id);
        $before = LedgerEntry::orderBy('id')->get()->toJson();
        $this->get('/transactions')->assertOk()->assertSee('Alice History')->assertSee('Bob History')->assertSee('Canceled')->assertSee('Payment canceled')->assertSee('Duplicate collection')->assertSee("\u{20B1}", false);
        $this->get('/transactions?search=Alice&type=PAYMENT&from=2026-09-03&to=2026-09-03')->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->total() === 1 && $rows->first()->payment_id === $payment->id)->assertDontSee('Bob History');
        $this->get('/transactions?type=PAYMENT_REVERSAL')->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->total() === 1 && $rows->first()->effective_date === '2026-10-05');
        $this->get('/transactions?from=2026-09-04&to=2026-09-30')->assertOk()->assertSee('No transactions found.');
        $this->assertSame($before, LedgerEntry::orderBy('id')->get()->toJson());
    }
    public function test_pagination_is_stable_and_preserves_filters(): void
    {
        $admin = $this->signIn(); $person = Borrower::create(['name'=>'History Person']);
        $loan = app(LoanService::class)->createLoan($person->id, '1000', today()->toDateString(), $admin->id);
        for ($i = 0; $i < 22; $i++) LedgerEntry::create(['loan_id'=>$loan->id,'type'=>'ADJUSTMENT','amount'=>'1.00','effective_date'=>today()->toDateString(),'notes'=>'Audit fixture']);
        $this->get('/transactions?search=History&type=ADJUSTMENT')->assertOk()->assertViewHas('transactions', function ($rows) {
            return $rows->total() === 22 && $rows->count() === 20 && str_contains($rows->nextPageUrl(), 'type=ADJUSTMENT') && str_contains($rows->nextPageUrl(), 'search=History') && $rows->first()->id > $rows->last()->id;
        });
        $this->get('/transactions?search=History&type=ADJUSTMENT&page=2')->assertOk()->assertViewHas('transactions', fn ($rows) => $rows->count() === 2);
    }
}
