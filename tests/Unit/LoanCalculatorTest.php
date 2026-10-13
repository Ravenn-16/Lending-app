<?php
namespace Tests\Unit;
use App\Services\LoanCalculator;
use PHPUnit\Framework\TestCase;
class LoanCalculatorTest extends TestCase
{
    private function check($condition, $message) { $this->assertTrue($condition, $message); }
    private function rejects(callable $action, $message) {
        try { $action(); } catch (\InvalidArgumentException | \OverflowException $e) { $this->assertTrue(true); return; }
        $this->fail($message);
    }
    private function event($type, $amount, $date, $id = 1) { return ['type' => $type, 'amount' => $amount, 'effective_date' => $date, 'id' => $id]; }
    public function test_ledger_interest_and_due_date_regressions(): void {
$loan = ['principal' => '10000.00', 'date_borrowed' => '2026-01-15'];
$initial = [$this->event('PRINCIPAL', '10000.00', '2026-01-15'), $this->event('INTEREST', '1000.00', '2026-01-15', 2)];
$this->check(LoanCalculator::replay($loan, $initial, '2026-01-15')['balance_cents'] === 1100000, 'initial interest');
$this->check(LoanCalculator::replay($loan, $initial, '2026-01-14')['balance_cents'] === 0, 'before origination');
$this->check(LoanCalculator::replay($loan, $initial, '2026-03-15')['balance_cents'] === 1331000, 'two months compound');
$partial = [...$initial, $this->event('PAYMENT', '3000.00', '2026-02-05', 3)];
$this->check(LoanCalculator::replay($loan, $partial, '2026-02-15')['balance_cents'] === 880000, 'README example');
$sameDay = [...$initial, $this->event('PAYMENT', '3000.00', '2026-02-15', 3)];
$this->check(LoanCalculator::replay($loan, $sameDay, '2026-02-15')['balance_cents'] === 880000, 'payments before anniversary interest');
$full = [...$initial, $this->event('PAYMENT', '11000.00', '2026-02-15', 3)];
$this->check(LoanCalculator::replay($loan, $full, '2026-06-15')['balance_cents'] === 0, 'full payment stops interest');
$this->check(LoanCalculator::replay($loan, $initial, '2026-02-15', true)['balance_cents'] === 1100000, 'same-day payoff quote');
$posted = [...$initial, $this->event('INTEREST', '1100.00', '2026-02-15', 3)];
$this->check(LoanCalculator::replay($loan, $posted, '2026-02-15')['balance_cents'] === 1210000, 'no duplicate stored interest');
$this->check(LoanCalculator::replay($loan, [], '2026-02-15')['balance_cents'] === 1210000, 'legacy missing principal and initial interest');
$this->check(LoanCalculator::anniversary('2026-01-31', 1) === '2026-02-28', 'February clamp');
$this->check(LoanCalculator::anniversary('2026-01-31', 2) === '2026-03-31', 'original day preserved');
$this->check(LoanCalculator::anniversary('2024-01-31', 1) === '2024-02-29', 'leap year');
$this->check(LoanCalculator::anniversary('2026-12-31', 2) === '2027-02-28', 'year transition');
foreach (['2026-02-11' => 'ACTIVE', '2026-02-12' => 'DUE_SOON', '2026-02-15' => 'DUE_SOON',
          '2026-02-16' => 'OVERDUE', '2026-03-15' => 'OVERDUE'] as $day => $status) {
    $this->check(LoanCalculator::replay($loan, $initial, $day)['status'] === $status, 'status on ' . $day);
}
$this->check(LoanCalculator::replay($loan, $initial, '2026-03-15')['next_due_date'] === '2026-02-15', 'overdue date retained');
$this->check(LoanCalculator::replay($loan, [...$initial, $this->event('PAYMENT', '12000.00', '2026-01-20')], '2026-01-20')['balance_cents'] === -100000, 'legacy credit is visible');
$reversed = [...$full, $this->event('PAYMENT_REVERSAL', '11000.00', '2026-04-15', 4)];
$this->check(LoanCalculator::replay($loan, $reversed, '2026-03-15')['balance_cents'] === 0, 'void preserves history before reversal');
$this->check(LoanCalculator::replay($loan, $reversed, '2026-04-15')['balance_cents'] === 1210000, 'void restores balance before same-day interest');
$this->check(LoanCalculator::cents('0.01') === 1 && LoanCalculator::decimal(101) === '1.01', 'exact centavos');
foreach (['0.001', '1e3', 'NaN', '10000000000'] as $bad) $this->rejects(fn() => LoanCalculator::cents($bad), 'reject ' . $bad);


    }
}
