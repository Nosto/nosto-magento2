<?php

namespace Nosto\Tagging\Helper;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Format helper used for common formatting tasks.
 */
class Format extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \NostoFormatterPrice the nosto price formatter.
     */
    protected $_priceFormatter;

    /**
     * @var \NostoFormatterDate the nosto date formatter.
     */
    protected $_dateFormatter;

    /**
     * Constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context the context.
     * @param \NostoFormatterPrice                  $priceFormatter the nosto price formatter.
     * @param \NostoFormatterDate                   $dateFormatter the nosto date formatter.
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \NostoFormatterPrice $priceFormatter,
        \NostoFormatterDate $dateFormatter
    ) {
        parent::__construct($context);

        $this->_priceFormatter = $priceFormatter;
        $this->_dateFormatter = $dateFormatter;
    }

    /**
     * Formats a \NostoPrice object, e.g. "1234.56".
     *
     * @param \NostoPrice $price the price to format.
     * @return string the formatted price.
     */
    public function formatPrice(\NostoPrice $price)
    {
        return $this->_priceFormatter->format(
            $price,
            new \NostoPriceFormat(2, '.', '')
        );
    }

    /**
     * Formats a \NostoDate object, e.g. "2015-12-24";
     *
     * @param \NostoDate $date the date to format.
     * @return string the formatted date.
     */
    public function formatDate(\NostoDate $date)
    {
        return $this->_dateFormatter->format(
            $date,
            new \NostoDateFormat(\NostoDateFormat::YMD)
        );
    }
}
