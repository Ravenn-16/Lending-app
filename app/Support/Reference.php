<?php

namespace App\Support;

class Reference
{
    public static function format(string $type, int $id): string
    {
        $prefix = ['Loan' => 'LN', 'Borrower' => 'BR', 'Payment' => 'PAY', 'LedgerEntry' => 'TXN', 'Admin' => 'ADM'][$type];
        return $prefix.'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
