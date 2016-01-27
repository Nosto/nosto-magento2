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
    )
    {
        parent::__construct($context);

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
