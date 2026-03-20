<?php

namespace Ksfraser\QifParser\Services;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * Deterministic FITID Generation Service
 * 
 * Purpose: Generates a stable unique identifier for transactions to prevent 
 * duplicate imports across multiple runs or identical same-day transactions.
 * 
 * Algorithm: SHA256 (Date + Account + Payee + Amount + Sequence)
 * 
 * @requirement FR-2.2.0 (Identity Integrity)
 */
class FitidService
{
    /**
     * @param QifTransaction $transaction
     * @param string $accountNumber
     * @param string $bankId
     * @return string
     */
    public function generate(QifTransaction $transaction, string $accountNumber, string $bankId): string
    {
        $components = [
            $transaction->date ?? '0001-01-01',
            $bankId,
            $accountNumber,
            $transaction->payee ?? 'UnnamedPayee',
            $transaction->amount,
            $transaction->fileSequence // Crucial for identical same-day transactions
        ];

        $hash = hash('sha256', implode('|', $components));
        
        // Match the 32-character or length format used in standard ofx parsers
        return strtoupper(substr($hash, 0, 16));
    }
}
