<?php
namespace Tests\Feature;

use App\Models\{Admin, Borrower, LedgerEntry};
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Admin
    {
        $this->travelTo(now()->setDate(2026, 10, 8)->startOfDay());
        $admin = Admin::create(['username' => 'reports', 'password_hash' => bcrypt('Test-password-123!')]);
        $this->actingAs($admin);
        return $admin;
    }

    public function test_access_validation_and_empty_reports(): void
    {
        foreach (['', '?format=csv', '?format=print'] as $query) $this->get('/reports'.$query)->assertRedirect('/login');
        $this->signIn();
        foreach (['collections', 'releases', 'interest', 'outstanding', 'due'] as $type) {
            $this->get('/reports?type='.$type)->assertOk()->assertSee('No records match this report.');
        }
        foreach (['type=unknown', 'type[]=due', 'from=2026-02-30', 'from=2026-10-09', 'from=2026-10-05&to=2026-10-01', 'page=0', 'format=pdf', 'search[]=bad'] as $query) {
            $this->getJson('/reports?'.$query)->assertUnprocessable();
        }
    }

    public function test_period_totals_reversals_and_historical_snapshots_are_read_only(): void
    {
        $admin = $this->signIn();
        $person = Borrower::create(['name' => 'Report Alice']);
        $service = app(LoanService::class);
        $loan = $service->createLoan($person->id, '1000', '2026-09-08', $admin->id);
        $payment = $service->recordPayment($loan->id, '100', '2026-09-15', $admin->id);
        $service->voidPayment($payment->id, 'Correction', $admin->id);
        $before = LedgerEntry::orderBy('id')->get()->toJson();
        $this->get('/reports?type=collections&from=2026-09-01&to=2026-09-30')->assertOk()
            ->assertViewHas('report', fn ($r) => $r['reportTotal'] === 10000 && $r['totals'] === ['releases' => 100000, 'collections' => 10000, 'interest' => 10000, 'outstanding' => 100000]);
        $this->get('/reports?type=collections&from=2026-10-08&to=2026-10-08')->assertOk()
            ->assertSee('Payment canceled')->assertViewHas('report', fn ($r) => $r['reportTotal'] === -10000);
        $this->get('/reports?type=interest&from=2026-10-08&to=2026-10-08')->assertOk()
            ->assertSee('Calculated interest')->assertViewHas('report', fn ($r) => $r['reportTotal'] === 11000);
        $this->get('/reports?type=outstanding&from=2026-09-30&to=2026-09-30')->assertOk()
            ->assertViewHas('report', fn ($r) => $r['reportTotal'] === 100000 && $r['rows']->count() === 1);
        $this->get('/reports?type=outstanding&to=2026-09-07')->assertOk()->assertViewHas('report', fn ($r) => $r['rows']->isEmpty());
        $this->assertSame($before, LedgerEntry::orderBy('id')->get()->toJson());
    }

    public function test_due_list_excludes_paid_and_later_loans_and_search_filters_totals(): void
    {
        $admin = $this->signIn(); $service = app(LoanService::class);
        $alice = Borrower::create(['name' => 'Alice']); $bob = Borrower::create(['name' => 'Bob']);
        foreach (['2026-09-07', '2026-09-08', '2026-09-11', '2026-09-12'] as $date) $service->createLoan($alice->id, '1000', $date, $admin->id);
        $paid = $service->createLoan($bob->id, '1000', '2026-09-10', $admin->id);
        $service->recordPayment($paid->id, '1100', '2026-09-11', $admin->id);
        $this->get('/reports?type=due')->assertOk()->assertViewHas('report', fn ($r) => $r['rows']->count() === 3);
        $this->get('/reports?type=outstanding&search=Bob')->assertOk()->assertViewHas('report', fn ($r) => $r['rows']->isEmpty() && $r['totals']['outstanding'] === 0);
    }

    public function test_export_and_print_include_all_rows_and_csv_names_are_safe(): void
    {
        $admin = $this->signIn();
        $person = Borrower::create(['name' => '=Report formula']);
        $service = app(LoanService::class);
        for ($i = 0; $i < 22; $i++) $service->createLoan($person->id, '100', '2026-10-01', $admin->id);
        $query = 'type=releases&from=2026-10-01&to=2026-10-08';
        $this->get('/reports?'.$query)->assertOk()->assertViewHas('rows', fn ($rows) => $rows->total() === 22 && $rows->count() === 20);
        $this->get('/reports?'.$query.'&page=2')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 2);
        $this->get('/reports?'.$query.'&format=print')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->count() === 22)->assertSee('Print report')
            ->assertSee('LN-000001')->assertSee('BR-000001')->assertSee('TXN-000001')
            ->assertSee('Prepared by')->assertSee('RPT-RELEASES')->assertSee('Money summary');
        $csv = $this->get('/reports?'.$query.'&format=csv')->assertOk()->assertDownload('lending-releases-2026-10-08.csv')->streamedContent();
        $this->assertSame(22, substr_count($csv, "'=Report formula"));
        $this->assertStringContainsString('2200.00', $csv);
        $this->assertStringContainsString('LN-000001', $csv);
        $this->assertStringContainsString('BR-000001', $csv);
        $this->assertStringContainsString('TXN-000001', $csv);
        $this->assertSame('LN-1234567', \App\Support\Reference::format('Loan', 1234567));
    }
}
