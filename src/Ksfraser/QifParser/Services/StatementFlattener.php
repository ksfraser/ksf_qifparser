<?php

namespace Ksfraser\QifParser\Services;

use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Entities\QifStatement;

/**
 * Service for Flattening Split Transactions
 * 
 * Purpose: Converts hierarchical QIF splits into one 'Summary' row and 
 * multiple 'Child' (Split-Debit/Credit) rows for compatibility with 
 * bank_import / bi_transactions storage.
 * 
 * @requirement FR-2.1.4 (Split Mapping)
 * @requirement FR-1.1 (Integration Parity)
 */
class StatementFlattener
{
    /**
     * @param QifStatement $statement
     * @return array[] Array of associative arrays matching 'bi_transactions' schema
     */
    public function flatten(QifStatement $statement): array
    {
        $flattenedRows = [];

        foreach ($statement->transactions as $transaction) {
            // 1. Add Summary Row (The Master Transaction)
            $flattenedRows[] = $this->createSummaryRow($transaction, $statement);

            // 2. Add Split Rows (The Individual Entries)
            foreach ($transaction->splits as $index => $split) {
                $flattenedRows[] = $this->createSplitRow($transaction, $split, $index, $statement);
            }
        }

        return $flattenedRows;
    }

    /**
     * @param QifTransaction $transaction
     * @param QifStatement $statement
     * @return array
     */
    private function createSummaryRow(QifTransaction $transaction, QifStatement $statement): array
    {
        return [
            'fitid' => $transaction->fitid,
            'date' => $transaction->date,
            'amount' => $transaction->amount,
            'name' => $transaction->payee,
            'memo' => $transaction->memo,
            'type' => empty($transaction->splits) ? 'TRANSACTION' : 'SPLIT_SUMMARY',
            'bank_id' => $statement->bankId,
            'account_id' => $statement->accountId,
            'currency' => $statement->currency
        ];
    }

    /**
     * @param QifTransaction $transaction
     * @param mixed $split
     * @param int $index
     * @param QifStatement $statement
     * @return array
     */
    private function createSplitRow(QifTransaction $transaction, $split, int $index, QifStatement $statement): array
    {
        return [
            // Use a derived fitid for the split line to ensure uniqueness and traceability
            'fitid' => $transaction->fitid . '-SPLIT-' . $index,
            'date' => $transaction->date,
            'amount' => $split->amount,
            'name' => 'Split: ' . ($split->category ?? 'Categorization'),
            'memo' => $split->memo ?? $transaction->memo,
            'type' => $split->amount < 0 ? 'SPLIT-DEBIT' : 'SPLIT-CREDIT',
            'bank_id' => $statement->bankId,
            'account_id' => $statement->accountId,
            'currency' => $statement->currency
        ];
    }
}
