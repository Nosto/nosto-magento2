<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class ActiveVariationTagging implements SectionSourceInterface
{
    /**
     * @var NostoHelperData
     */
    private $nostoHelperData;

    /**
     * @var NostoHelperCustomer
     */
    private $nostoHelperCustomer;

    /**
     * @var NostoHelperScope
     */
    private $nostoHelperScope;

    /**
     * @var NostoHelperVariation
     */
    private $nostoHelperVariation;

    /**
     * @var NostoLogger
     */
    private $nostoLogger;

    /**
     * ActiveVariationTagging constructor.
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperCustomer $nostoHelperCustomer
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoLogger $nostoLogger
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperCustomer $nostoHelperCustomer,
        NostoHelperScope $nostoHelperScope,
        NostoHelperVariation $nostoHelperVariation,
        NostoLogger $nostoLogger
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperCustomer = $nostoHelperCustomer;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperVariation = $nostoHelperVariation;
        $this->nostoLogger = $nostoLogger;
    }

    /**
     * @inheritdoc
     */
    public function getSectionData()
    {
        $data = [];
        $store = $this->nostoHelperScope->getStore(true);
        if ($this->nostoHelperData->isPricingVariationEnabled($store)
            && !$this->nostoHelperVariation->isDefaultVariationCode(
                $this->nostoHelperCustomer->getGroupCode()
            )
        ) {
            try {
                $data['active_variation'] = $this->nostoHelperCustomer->getGroupCode();
            } catch (\Exception $e) {
                $this->nostoLogger->exception($e);
            }
        }

        return $data;
    }
}
