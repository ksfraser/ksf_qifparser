<?php

namespace Ksfraser\QifParser\Entities;

/**
 * Payee entity holding the payee name context and optional multi-line address.
 *
 * @requirement FR-2.1.1 (Payee & Address Support)
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
