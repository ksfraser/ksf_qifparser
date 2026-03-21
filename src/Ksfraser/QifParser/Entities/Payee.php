<?php

namespace Ksfraser\QifParser\Entities;

/**
 * @deprecated Use \Ksfraser\Contact\DTO\ContactData instead.
 *             This class is retained only for backward compatibility.
 *             Address lines map to ContactData::$address_line_1 / $address_line_2.
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
     * @deprecated Use ContactData::$address_line_1 / $address_line_2 instead
     */
    public function addAddressLine(string $line): void
    {
        $this->address[] = $line;
    }
}
