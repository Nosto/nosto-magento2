<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Helper;

/** @noinspection PhpIncludeInspection */
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Format helper used for common formatting tasks.
 */
class Format extends AbstractHelper
{
    /**
     * @var \Nosto\Sdk\NostoFormatterPrice the nosto price formatter.
     */
    protected $_priceFormatter;

    /**
     * @var \Nosto\Sdk\NostoFormatterDate the nosto date formatter.
     */
    protected $_dateFormatter;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param \Nosto\Sdk\NostoFormatterPrice $priceFormatter the nosto price formatter.
     * @param \Nosto\Sdk\NostoFormatterDate $dateFormatter the nosto date formatter.
     */
    public function __construct(
        Context $context,
        \Nosto\Sdk\NostoFormatterPrice $priceFormatter,
        \Nosto\Sdk\NostoFormatterDate $dateFormatter
    ) {
        parent::__construct($context);

        $this->_priceFormatter = $priceFormatter;
        $this->_dateFormatter = $dateFormatter;
    }

    /**
     * Formats a \Nosto\Sdk\NostoPrice object, e.g. "1234.56".
     *
     * @param \Nosto\Sdk\NostoPrice $price the price to format.
     * @return string the formatted price.
     */
    public function formatPrice(\Nosto\Sdk\NostoPrice $price)
    {
        return $this->_priceFormatter->format(
            $price,
            new \Nosto\Sdk\NostoPriceFormat(2, '.', '')
        );
    }

    /**
     * Formats a \Nosto\Sdk\NostoDate object, e.g. "2015-12-24";
     *
     * @param \Nosto\Sdk\NostoDate $date the date to format.
     * @return string the formatted date.
     */
    public function formatDate(\Nosto\Sdk\NostoDate $date)
    {
        return $this->_dateFormatter->format(
            $date,
            new \Nosto\Sdk\NostoDateFormat(\Nosto\Sdk\NostoDateFormat::YMD)
        );
    }
}
