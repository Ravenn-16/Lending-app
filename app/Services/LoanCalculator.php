<?php
namespace App\Services;

use App\Models\Loan;
use DateTimeImmutable;
use InvalidArgumentException;
use OverflowException;

class LoanCalculator
{
    public function summary(Loan $loan, ?string $asOfDate = null, bool $quote = false): array
    {
        $loan->loadMissing('ledgerEntries');
        return self::replay($loan->toArray(), $loan->ledgerEntries->toArray(), $asOfDate ?? today()->toDateString(), $quote);
    }

    public static function cents($amount) {
        $value = (string)$amount;
        if (!preg_match('/^(-?)([0-9]+)(?:[.]([0-9]{1,2}))?$/D', $value, $m)) {
            throw new InvalidArgumentException('Amounts must have at most two decimal places.');
        }
        if (strlen(ltrim($m[2], '0')) > 10) throw new InvalidArgumentException('Amount is too large.');
        return ($m[1] === '-' ? -1 : 1) * ((int)$m[2] * 100 + (int)str_pad($m[3] ?? '', 2, '0'));
    }

    public static function decimal($cents) {
        return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string)(abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function anniversary($date, $months) {
        $start = new DateTimeImmutable($date);
        $month = $start->modify('first day of this month')->modify('+' . (int)$months . ' months');
        return $month->setDate((int)$month->format('Y'), (int)$month->format('m'),
            min((int)$start->format('d'), (int)$month->format('t')))->format('Y-m-d');
    }

    /** Existing interest entries override derived charges on their date.
     * quote=true omits the as-of date's derived monthly charge: payments go first.
     * Reads never write interest, so backdated payments recalculate deterministically.
     */
    public static function replay(array $loan, array $entries, $asOfDate, $quote = false) {
        $start = $loan['date_borrowed'];
        $events = [];
        $principalFound = false;
        $interestDates = [];
        foreach ($entries as $entry) {
            $principalFound = $principalFound || $entry['type'] === 'PRINCIPAL';
            if ($entry['type'] === 'INTEREST') $interestDates[$entry['effective_date']] = true;
            if ($entry['effective_date'] <= $asOfDate) $events[] = $entry;
        }
        if (!$principalFound && $start <= $asOfDate) {
            $events[] = ['type' => 'PRINCIPAL', 'amount' => $loan['principal'], 'effective_date' => $start, 'id' => 0];
        }
        if ($start <= $asOfDate && !isset($interestDates[$start])) {
            $events[] = ['type' => 'INTEREST', 'amount' => self::decimal(intdiv(self::cents($loan['principal']) + 5, 10)),
                'effective_date' => $start, 'id' => 0, 'notes' => 'Initial 10% interest (calculated)', 'calculated' => true];
        }
        for ($month = 1; ($date = self::anniversary($start, $month)) <= $asOfDate; $month++) {
            if (!isset($interestDates[$date]) && !($quote && $date === $asOfDate)) {
                $events[] = ['type' => 'INTEREST', 'amount' => null, 'effective_date' => $date,
                    'id' => PHP_INT_MAX, 'notes' => 'Monthly 10% interest (calculated)', 'calculated' => true];
            }
        }
        $priority = ['PRINCIPAL' => 0, 'PAYMENT' => 2, 'PAYMENT_REVERSAL' => 3, 'ADJUSTMENT' => 4, 'INTEREST' => 5];
        usort($events, function($a, $b) use ($priority, $start) {
            $aPriority = $a['type'] === 'INTEREST' && $a['effective_date'] === $start ? 1 : $priority[$a['type']];
            $bPriority = $b['type'] === 'INTEREST' && $b['effective_date'] === $start ? 1 : $priority[$b['type']];
            return [$a['effective_date'], $aPriority, $a['id'] ?? 0] <=> [$b['effective_date'], $bPriority, $b['id'] ?? 0];
        });
        $balance = $interest = $paid = $principal = 0;
        $history = [];
        $overpayment = false;
        foreach ($events as $event) {
            if ($event['amount'] === null) {
                if ($balance <= 0) continue;
                $amount = intdiv($balance + 5, 10);
                $event['amount'] = self::decimal($amount);
            } else {
                $amount = self::cents($event['amount']);
            }
            switch ($event['type']) {
                case 'PRINCIPAL': $balance += $amount; $principal += $amount; break;
                case 'INTEREST': $balance += $amount; $interest += $amount; break;
                case 'PAYMENT': $balance -= $amount; $paid += $amount; break;
                case 'PAYMENT_REVERSAL': $balance += $amount; $paid -= $amount; break;
                case 'ADJUSTMENT': $balance += $amount; break;
            }
            if (abs($balance) > 999999999999) throw new OverflowException('Loan balance exceeds the supported amount.');
            if ($balance < 0) $overpayment = true;
            $event['running_balance'] = $balance / 100;
            $history[] = $event;
        }
        $due = self::anniversary($start, 1);
        $days = (int)(new DateTimeImmutable($asOfDate))->diff(new DateTimeImmutable($due))->format('%r%a');
        $status = $balance <= 0 ? 'FULLY_PAID' : ($days < 0 ? 'OVERDUE' : ($days <= 3 ? 'DUE_SOON' : 'ACTIVE'));
        return ['balance' => $balance / 100, 'balance_cents' => $balance,
            'interest_accrued' => $interest / 100, 'total_paid' => $paid / 100,
            'principal_amount' => $principal / 100, 'total_amount_owed' => $balance / 100,
            'next_due_date' => $balance > 0 ? $due : null, 'status' => $status,
            'has_overpayment' => $overpayment, 'history' => $history];
    }

}
