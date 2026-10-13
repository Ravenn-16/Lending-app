<?php
namespace App\Services;

use App\Models\{Admin, Borrower, Loan, Payment, LedgerEntry};
use App\Exceptions\LendingValidationException;
use App\Rules\PlainText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanService
{
    private function actor(int $adminId): void
    {
        if (!Admin::whereKey($adminId)->exists()) throw new InvalidArgumentException('A valid administrator is required.');
    }

    private function validateReversal(Loan $loan, Payment $payment): void
    {
        $entries = $loan->ledgerEntries()->lockForUpdate()->get();
        $posted = $entries->where('type', 'PAYMENT')->where('payment_id', $payment->id);
        if ($posted->count() !== 1 || $posted->first()->amount !== $payment->amount || $posted->first()->effective_date !== $payment->payment_date ||
            $entries->where('type', 'PAYMENT_REVERSAL')->where('payment_id', $payment->id)->isNotEmpty() ||
            $payment->payment_date < $loan->date_borrowed || $payment->payment_date > today()->toDateString() || LoanCalculator::cents($payment->amount) <= 0) {
            throw new InvalidArgumentException('Payment history is inconsistent. Review the ledger before reversing this payment.');
        }
        $history = $entries->toArray();
        $history[] = ['id' => PHP_INT_MAX, 'type' => 'PAYMENT_REVERSAL', 'amount' => $payment->amount, 'effective_date' => today()->toDateString()];
        LoanCalculator::replay($loan->toArray(), $history, today()->toDateString());
    }

    private function amount(string $value): int
    {
        $cents = LoanCalculator::cents($value);
        if ($cents <= 0) throw new InvalidArgumentException('Amount must be greater than zero.');
        return $cents;
    }

    private function validDate(string $date, string $field): void
    {
        $parsed = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1900-01-01' || $date > today()->toDateString()) {
            throw new LendingValidationException($field, 'Enter a valid date between January 1, 1900 and today.');
        }
    }

    private function ledger(int $loanId, string $type, int $amount, string $date, int $adminId, ?int $paymentId = null, string $reason = ''): void
    {
        LedgerEntry::create(['loan_id' => $loanId, 'type' => $type, 'amount' => LoanCalculator::decimal($amount),
            'effective_date' => $date, 'payment_id' => $paymentId,
            'notes' => 'Admin #'.$adminId.($reason !== '' ? ': '.$reason : '')]);
    }

    public function createLoan(int $borrowerId, string $principal, string $date, int $adminId): Loan
    {
        $this->actor($adminId);
        $this->validDate($date, 'date_borrowed');
        $cents = $this->amount($principal);
        if ($cents > 909090909090) throw new LendingValidationException('principal', 'Principal plus initial interest exceeds the supported balance.');
        LoanCalculator::replay(['principal' => LoanCalculator::decimal($cents), 'date_borrowed' => $date], [], today()->toDateString());
        return DB::transaction(function () use ($borrowerId, $cents, $date, $adminId) {
            Borrower::query()->lockForUpdate()->findOrFail($borrowerId);
            $loan = Loan::create(['borrower_id' => $borrowerId, 'principal' => LoanCalculator::decimal($cents), 'date_borrowed' => $date]);
            $this->ledger($loan->id, 'PRINCIPAL', $cents, $date, $adminId);
            $this->ledger($loan->id, 'INTEREST', intdiv($cents + 5, 10), $date, $adminId, null, 'Initial 10% interest');
            return $loan;
        }, 3);
    }

    public function recordPayment(int $loanId, string $amount, string $date, int $adminId, ?int $borrowerId = null): Payment
    {
        $this->actor($adminId);
        $this->validDate($date, 'payment_date');
        $cents = $this->amount($amount);
        return DB::transaction(function () use ($loanId, $cents, $date, $adminId, $borrowerId) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loanId);
            if ($borrowerId !== null && $loan->borrower_id !== $borrowerId) throw new LendingValidationException('loan_id', 'Choose a loan belonging to the selected borrower.');
            if ($date < $loan->date_borrowed) throw new LendingValidationException('payment_date', 'Payment cannot precede the loan date.');
            $entries = $loan->ledgerEntries()->lockForUpdate()->get()->toArray();
            $quote = LoanCalculator::replay($loan->toArray(), $entries, $date, true);
            if ($quote['balance_cents'] <= 0) throw new LendingValidationException('payment_amount', 'This loan has no payable balance on the selected date.');
            if ($cents > $quote['balance_cents']) throw new InvalidArgumentException('Payment exceeds the balance on the selected date.');
            $entries[] = ['id' => PHP_INT_MAX, 'type' => 'PAYMENT', 'amount' => LoanCalculator::decimal($cents), 'effective_date' => $date];
            $summary = LoanCalculator::replay($loan->toArray(), $entries, today()->toDateString());
            $closing = [];
            foreach ($summary['history'] as $entry) $closing[$entry['effective_date']] = $entry['running_balance'];
            foreach ($closing as $day => $balance) {
                if ($day >= $date && $balance < 0) throw new InvalidArgumentException('This payment conflicts with a later payment. Review the loan history.');
            }
            $payment = Payment::create(['loan_id' => $loanId, 'amount' => LoanCalculator::decimal($cents), 'payment_date' => $date, 'status' => 'POSTED']);
            $this->ledger($loanId, 'PAYMENT', $cents, $date, $adminId, $payment->id);
            return $payment;
        }, 3);
    }

    public function voidPayment(int $paymentId, string $reason, int $adminId): Payment
    {
        $this->actor($adminId);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) throw new InvalidArgumentException('Enter a void reason of 1 to 500 characters.');
        Validator::make(['reason' => $reason], ['reason' => [new PlainText(true)]])->validate();
        return DB::transaction(function () use ($paymentId, $reason, $adminId) {
            $loanId = Payment::findOrFail($paymentId)->loan_id;
            $loan = Loan::query()->lockForUpdate()->findOrFail($loanId);
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            if ($payment->status !== 'POSTED') throw new InvalidArgumentException('This payment has already been voided.');
            $this->validateReversal($loan, $payment);
            $payment->update(['status' => 'VOIDED', 'voided_at' => now()]);
            $this->ledger($loanId, 'PAYMENT_REVERSAL', LoanCalculator::cents($payment->amount), today()->toDateString(), $adminId, $paymentId, $reason);
            return $payment;
        }, 3);
    }
}
