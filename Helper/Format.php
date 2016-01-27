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
     * @param \NostoFormatterPrice $priceFormatter the nosto price formatter.
     * @param \NostoFormatterDate $dateFormatter the nosto date formatter.
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \NostoFormatterPrice $priceFormatter,
        \NostoFormatterDate $dateFormatter
    )
    {
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
