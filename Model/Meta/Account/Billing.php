<?php

namespace Nosto\Tagging\Model\Meta\Account;

class Billing implements \NostoAccountMetaBillingInterface
{
    /**
     * @var \NostoCountryCode country ISO (ISO 3166-1 alpha-2) code for billing details.
     */
    protected $_country;

    /**
     * The 2-letter ISO code (ISO 3166-1 alpha-2) for billing details country.
     *
     * @return \NostoCountryCode the country code.
     */
    public function getCountry()
    {
        return $this->_country;
    }

    // todo

    public function setCountry(\NostoCountryCode $country)
    {
        $this->_country = $country;
    }
}
