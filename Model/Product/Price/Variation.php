<?php

namespace Nosto\Tagging\Model\Product\Price;

class Variation implements \NostoProductPriceVariationInterface
{
    /**
     * @var \NostoPriceVariation the price variation ID, e.g. the currency code.
     */
    protected $_id;

    /**
     * @var \NostoCurrencyCode the currency code (ISO 4217) for the price variation.
     */
    protected $_currency;

    /**
     * @var \NostoPrice the price of the variation including possible discounts and taxes.
     */
    protected $_price;

    /**
     * @var \NostoPrice the list price of the variation without discounts but incl taxes.
     */
    protected $_listPrice;

    /**
     * @var \NostoProductAvailability the availability of the price variation.
     */
    protected $_availability;

    /**
     * Constructor.
     *
     * Sets up this Value Object.
     *
     * @param \NostoPriceVariation $id the variation ID.
     * @param \NostoCurrencyCode $currency the ISO 4217 currency code.
     * @param \NostoPrice $price the price.
     * @param \NostoPrice $unitPrice the unit price.
     * @param \NostoProductAvailability $availability the availability.
     */
    public function __construct(
        \NostoPriceVariation $id,
        \NostoCurrencyCode $currency,
        \NostoPrice $price,
        \NostoPrice $unitPrice,
        \NostoProductAvailability $availability
    ) {
        $this->id = $id;
        $this->currency = $currency;
        $this->price = $price;
        $this->listPrice = $unitPrice;
        $this->availability = $availability;
    }

    /**
     * Returns the price variation ID.
     *
     * @return \NostoPriceVariation the variation ID.
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Returns the currency code (ISO 4217) for the price variation.
     *
     * @return \NostoCurrencyCode the price currency code.
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * Returns the price of the variation including possible discounts and taxes.
     *
     * @return \NostoPrice the price.
     */
    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * Returns the list price of the variation without discounts but incl taxes.
     *
     * @return \NostoPrice the price.
     */
    public function getListPrice()
    {
        return $this->_listPrice;
    }

    /**
     * Returns the availability of the price variation, i.e. if it is in stock or not.
     *
     * @return \NostoProductAvailability the availability.
     */
    public function getAvailability()
    {
        return $this->_availability;
    }
}
