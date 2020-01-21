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
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;

/**
 * Nosto JS stub block
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Stub extends Template
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

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
     * Stub constructor.
     * @param Template\Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperCustomer $nostoHelperCustomer
     * @param NostoHelperVariation $nostoHelperVariation
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoHelperData $nostoHelperData,
        NostoHelperCustomer $nostoHelperCustomer,
        NostoHelperVariation $nostoHelperVariation,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperCustomer = $nostoHelperCustomer;
        $this->nostoHelperVariation = $nostoHelperVariation;
    }

    /**
     *
     * @return null
     */
    public function getAbstractObject()
    {
        return null;
    }

    /**
     * Returns if autoloading recommendations is disabled or not.
     *
     * @return boolean
     */
    public function isRecoAutoloadDisabled()
    {
        $store = $this->getNostoHelperScope()->getStore(true);
        // If price variations are used and the variation something else than
        // the default one we disable the autoload. For default variation
        // the sections are not loaded and loadRecommendations() is not called
        if ($this->nostoHelperData->isPricingVariationEnabled($store)
            && !$this->nostoHelperVariation->isDefaultVariationCode(
                $this->nostoHelperCustomer->getGroupCode()
            )
        ) {
            return true;
        }
        return false;
    }
}
