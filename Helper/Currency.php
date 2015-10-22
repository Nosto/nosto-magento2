<?php

namespace Nosto\Tagging\Helper;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Currency helper used for currency related tasks.
 */
class Currency extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \NostoHelperCurrency the Nosto currency helper.
     */
    protected $_currencyHelper;

    /**
     * Constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context the context.
     * @param \NostoHelperCurrency $currencyHelper the Nosto currency helper.
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \NostoHelperCurrency $currencyHelper
    ) {
        $this->_currencyHelper = $currencyHelper;
    }

    /**
     * Parses the format for a currency into a Nosto currency object.
     *
     * @param string $locale the locale to get the currency format in.
     * @param string $currencyCode the currency ISO 4217 code to get the currency in.
     * @return \NostoCurrency the parsed currency.
     */
    public function getCurrencyObject($locale, $currencyCode)
    {
        return $this->_currencyHelper->parseZendCurrencyFormat(
            $currencyCode,
            new \Zend_Currency($locale, $currencyCode)
        );
    }
}
