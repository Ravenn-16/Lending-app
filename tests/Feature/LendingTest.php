<?php
namespace Tests\Feature;
use App\Models\{Admin, Borrower, Loan, Payment, LedgerEntry};
use App\Services\{LoanService, LoanCalculator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, DB, RateLimiter};
use Tests\TestCase;

class LendingTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): Admin
    {
        return Admin::create(['username' => 'test_admin', 'password_hash' => Hash::make('Test-password-123!')]);
    }
    private function borrower(): Borrower
    {
        return Borrower::create(['name' => 'Test Person', 'location' => 'Manila', 'contact_number' => '09171234567']);
    }
    public function test_empty_lending_database_remains_usable(): void
    {
        $this->actingAs($this->admin());
        $this->get('/')->assertOk()->assertSee('0 people in your lending records.');
        $this->get('/borrowers')->assertOk()->assertSee('No borrowers found.');
        $this->get('/loans')->assertOk()->assertSee('No matching loans.');
        $this->get('/money')->assertOk()->assertSee('Choose a person');
        $this->get('/money?mode=payment')->assertOk()->assertSee('Choose a loan');
    }
    public function test_authentication_settings_and_logout(): void
    {
        $admin = $this->admin();
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/api/loans/1')->assertUnauthorized();
        $this->get('/login')->assertOk()->assertSee('Lending System');
        $this->post('/login', ['username' => 'test_admin', 'password' => 'incorrect'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'test_admin', 'password' => 'Test-password-123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->put('/settings/password', ['current_password' => 'wrong', 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])->assertSessionHasErrors();
        $this->put('/settings/password', ['current_password' => 'Test-password-123!', 'password' => 'New-password-123!', 'password_confirmation' => 'New-password-123!'])->assertRedirect();
        $this->assertTrue(Hash::check('New-password-123!', $admin->fresh()->password_hash));
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
    public function test_borrower_crud_search_and_restriction(): void
    {
        $this->actingAs($this->admin());
        $this->post('/borrowers', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Alice', 'contact_number' => '09171234567'])->assertRedirect();
        $borrower = Borrower::firstOrFail();
        $this->get('/borrowers?search=Alice')->assertOk()->assertSee('Alice');
        $this->put('/borrowers/'.$borrower->id, ['name' => 'Alice Updated'])->assertRedirect();
        $this->assertDatabaseHas('borrowers', ['id' => $borrower->id, 'name' => 'Alice Updated']);
        $this->post('/borrowers', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Invalid', 'contact_number' => '123'])->assertSessionHasErrors('contact_number');
        app(LoanService::class)->createLoan($borrower->id, '1000', today()->toDateString(), auth()->id());
        $this->delete('/borrowers/'.$borrower->id)->assertSessionHasErrors('borrower');
        $empty = $this->borrower();
        $this->delete('/borrowers/'.$empty->id)->assertRedirect('/borrowers');
        $this->assertDatabaseMissing('borrowers', ['id' => $empty->id]);
    }
    public function test_all_pages_render_and_legacy_bookmarks_redirect(): void
    {
        $admin = $this->admin(); $this->actingAs($admin);
        $borrower = $this->borrower();
        $loan = app(LoanService::class)->createLoan($borrower->id, '1000', today()->toDateString(), $admin->id);
        $payment = app(LoanService::class)->recordPayment($loan->id, '100', today()->toDateString(), $admin->id);
        foreach (['/', '/borrowers', '/borrowers/create', '/borrowers/'.$borrower->id, '/borrowers/'.$borrower->id.'/edit', '/loans/'.$loan->id,
            '/money', '/money?mode=payment', '/money?loan_id='.$loan->id, '/settings', '/payments/'.$payment->id.'/void'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['ACTIVE', 'DUE_SOON', 'OVERDUE', 'FULLY_PAID'] as $status) $this->get('/loans?status='.$status)->assertOk();
        $this->get('/borrowers/index.php')->assertRedirect('/borrowers');
        $this->get('/borrowers/view.php?id='.$borrower->id)->assertRedirect('/borrowers/'.$borrower->id);
        $this->get('/payments/add.php?loan_id='.$loan->id)->assertRedirect('/money?loan_id='.$loan->id);
        $this->get('/borrowers/99999')->assertNotFound();
    }
    public function test_loan_payment_and_void_workflows(): void
    {
        $admin = $this->admin(); $this->actingAs($admin); $borrower = $this->borrower(); $date = today()->toDateString();
        $this->post('/loans', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'borrower_id' => $borrower->id, 'principal' => '1000.00', 'date_borrowed' => $date])->assertRedirect();
        $loan = Loan::firstOrFail();
        $this->assertSame(110000, app(LoanCalculator::class)->summary($loan)['balance_cents']);
        $this->post('/payments', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'borrower_id' => $borrower->id, 'loan_id' => $loan->id, 'payment_amount' => '1100.01', 'payment_date' => $date])->assertSessionHasErrors('payment_amount');
        $this->post('/payments', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'borrower_id' => $borrower->id, 'loan_id' => $loan->id, 'payment_amount' => '100', 'payment_date' => '1900-01-01'])->assertSessionHasErrors('payment_date');
        $this->post('/payments', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'borrower_id' => $borrower->id, 'loan_id' => $loan->id, 'payment_amount' => '0.001', 'payment_date' => $date])->assertSessionHasErrors('payment_amount');
        $this->assertDatabaseCount('payments', 0);
        $this->post('/payments', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'borrower_id' => $borrower->id, 'loan_id' => $loan->id, 'payment_amount' => '100', 'payment_date' => $date])->assertRedirect('/loans/'.$loan->id);
        $payment = Payment::firstOrFail();
        $this->getJson('/api/borrowers/'.$borrower->id.'/loans')->assertOk()->assertJsonPath('0.id', $loan->id);
        $this->getJson('/api/loans/'.$loan->id)->assertOk()->assertJsonPath('payoff_amount', 1000);
        $this->getJson('/api/loans/'.$loan->id.'?payment_date=1900-01-01')->assertUnprocessable();
        $this->post('/payments/'.$payment->id.'/void', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post('/payments/'.$payment->id.'/void', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'reason' => 'Duplicate entry'])->assertRedirect();
        $this->post('/payments/'.$payment->id.'/void', ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'reason' => 'Repeated'])->assertSessionHasErrors('reason');
        $this->assertSame(1, LedgerEntry::where('type', 'PAYMENT_REVERSAL')->count());
        $this->assertDatabaseHas('ledger_entries', ['type' => 'PAYMENT_REVERSAL', 'notes' => 'Admin #'.$admin->id.': Duplicate entry']);
        $this->assertSame(110000, app(LoanCalculator::class)->summary($loan->fresh())['balance_cents']);
    }
    public function test_full_payment_on_anniversary_and_backdating(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 15)->startOfDay());
        $admin = $this->admin(); $borrower = $this->borrower(); $service = app(LoanService::class);
        $loan = $service->createLoan($borrower->id, '1000', '2026-02-15', $admin->id);
        $quote = app(LoanCalculator::class)->summary($loan, '2026-03-15', true);
        $this->assertSame(110000, $quote['balance_cents']);
        $service->recordPayment($loan->id, '1100', '2026-03-15', $admin->id);
        $this->assertSame(0, app(LoanCalculator::class)->summary($loan->fresh())['balance_cents']);
        $this->expectException(\InvalidArgumentException::class);
        $service->recordPayment($loan->id, '100', '2026-02-16', $admin->id);
    }
    public function test_adoption_migration_preserves_existing_records(): void
    {
        $admin = $this->admin(); $borrower = $this->borrower();
        app(LoanService::class)->createLoan($borrower->id, '100', today()->toDateString(), $admin->id);
        $before = DB::table('ledger_entries')->get()->toJson();
        $migration = require database_path('migrations/2026_10_02_000001_adopt_lending_tables.php');
        $migration->up();
        $this->assertSame($before, DB::table('ledger_entries')->get()->toJson());
        $this->assertDatabaseHas('admins', ['id' => $admin->id, 'password_hash' => $admin->password_hash]);
    }
    public function test_login_rate_limit(): void
    {
        for ($i = 0; $i < 20; $i++) $this->post('/login', ['username' => 'nobody', 'password' => 'wrong'])->assertStatus(302);
        $this->post('/login', ['username' => 'nobody', 'password' => 'wrong'])->assertStatus(429);
    }
}
