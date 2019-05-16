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
 * @copyright Copyright (c) 2013-2019 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Currency as NostoHelperCurrency;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;
use Nosto\Object\MarkupableString;

/**
 * Page type block used for outputting the variation identifier on the different pages.
 */
class Variation extends Template
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

    /**
     * @var NostoHelperCurrency
     */
    private $nostoHelperCurrency;

    /**
     * @var NostoHelperData
     */
    private $nostoHelperData;

    /**
     * @var NostoHelperCustomer
     */
    private $nostoHelperCustomer;

    /**
     * @var NostoHelperVariation
     */
    private $nostoHelperVariation;

    /**
     * Variation constructor.
     *
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperCurrency $nostoHelperCurrency
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperCustomer $nostoHelperCustomer
     * @param NostoHelperVariation $nostoHelperVariation
     * @param array $data
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoHelperCurrency $nostoHelperCurrency,
        NostoHelperData $nostoHelperData,
        NostoHelperCustomer $nostoHelperCustomer,
        NostoHelperVariation $nostoHelperVariation,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->nostoHelperCurrency = $nostoHelperCurrency;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperCustomer = $nostoHelperCustomer;
        $this->nostoHelperVariation = $nostoHelperVariation;
    }

    /**
     * Return the current variation id
     *
     * @return string
     */
    public function getVariationId()
    {
        $store = $this->nostoHelperScope->getStore(true);
        if ($this->nostoHelperData->isMultiCurrencyDisabled($store)
            && $this->nostoHelperData->isPricingVariationEnabled($store)
        ) {
            return $this->nostoHelperCustomer->getGroupCode();
        }

        return $store->getCurrentCurrencyCode();
    }

    /**
     * Checks if store uses more than one currency in order to decide whether to hide or show the
     * nosto_variation tagging.
     *
     * @return bool a boolean value indicating whether the store has more than one currency
     */
    public function hasMultipleCurrencies()
    {
        $store = $this->nostoHelperScope->getStore(true);
        return $this->nostoHelperCurrency->getCurrencyCount($store) > 1;
    }

    /**
     * Returns the HTML to render variation blocks
     *
     * @return MarkupableString|string
     */
    public function getAbstractObject()
    {
        $store = $this->nostoHelperScope->getStore(true);

        // We inject the active variation tag if the exchange rates are used or
        // if the price variations are used and the active variation is the
        // default one
        if ($this->nostoHelperCurrency->exchangeRatesInUse($store)
            || ($this->nostoHelperData->isPricingVariationEnabled($store)
                && $this->nostoHelperVariation->isDefaultVariationCode(
                    $this->nostoHelperCustomer->getGroupCode()
                )

            )
        ) {
            return new MarkupableString(
                $this->getVariationId(),
                'nosto_variation'
            );
        }
        return '';
    }
}
