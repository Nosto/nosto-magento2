<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Object\MarkupableString;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Currency as NostoHelperCurrency;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;

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
