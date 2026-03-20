<?php

namespace Ksfraser\QifParser\Entities;

/**
 * @requirement FR-2.1.3
 * @requirement FR-2.1.4
 */
class QifTransaction
{
    /** @var string|null Format: YYYY-MM-DD */
    public $date;

    /** @var float */
    public $amount = 0.0;

    /** @var string|null Check number or Action */
    public $number;

    /** @var string|null Name of payee */
    public $payee;

    /** @var string|null Transaction category */
    public $category;

    /** @var Payee|null Full payee details (including address) */
    public $payeeDetails;

    /** @var string|null Memo or description */
    public $memo;

    /** @var string Generated unique identifier */
    public $fitid;

    /** @var QifSplit[] Array of split entries */
    public $splits = [];

    /** @var int Internal sequence within file for duplicate handling */
    public $fileSequence = 0;

    /**
     * @param float $amount
     * @param string $category
     * @param string|null $memo
     */
    public function addSplit(float $amount, string $category, string $memo = null): void
    {
        $split = new QifSplit();
        $split->amount = $amount;
        $split->category = $category;
        $split->memo = $memo;
        $this->splits[] = $split;
    }

    /**
     * Verifies if the sum of splits equals the total amount.
     * @requirement FR-2.1.4
     * @return bool
     */
    public function validateSplits(): bool
    {
        if (empty($this->splits)) {
            return true;
        }

        $totalSplits = 0.0;
        foreach ($this->splits as $split) {
            $totalSplits += $split->amount;
        }

        return abs($this->amount - $totalSplits) < 0.00001;
    }
}

/**
 * Internal entity for Payee details
 */
class Payee
{
    /** @var string[] Array of address lines */
    public $address = [];

    /** @var string|null City name */
    public $city;

    /** @var string|null State/Province */
    public $state;

    /** @var string|null Postal/Zip code */
    public $postalCode;

    /** @var string|null Country name */
    public $country;

    /** @var string|null Phone number */
    public $phone;

    /**
     * @param string $line
     */
    public function addAddressLine(string $line): void
    {
        $this->address[] = $line;
    }
}

/**
 * Internal entity for Split items
 */
class QifSplit
{
    public $amount = 0.0;
    public $category;
    public $memo;
}
