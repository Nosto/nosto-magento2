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

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;

/**
 * Meta data block for outputting <meta> elements in the page <head>.
 * This block should be included on all pages.
 */
class Meta extends Template
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

    private $nostoHelperData;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param NostoHelperData $nostoHelperData the data helper.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param array $data optional data.
     */
    public function __construct(
        Context $context,
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * Returns the module version number.
     *
     * @return string the module version number.
     */
    public function getModuleVersion()
    {
        return $this->nostoHelperData->getModuleVersion();
    }

    /**
     * Returns the unique installation ID.
     *
     * @return string the unique ID.
     */
    public function getInstallationId()
    {
        return $this->nostoHelperData->getInstallationId();
    }

    /**
     * Returns the current stores language code in ISO 639-1 format.
     *
     * @return string the language code.
     */
    public function getLanguageCode()
    {
        $store = $this->nostoHelperScope->getStore(true);
        return substr($store->getConfig('general/locale/code'), 0, 2);
    }

    /**
     *
     * @return null
     */
    public function getAbstractObject()
    {
        return null;
    }
}
