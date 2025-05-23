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
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;
use Exception;

/**
 * Cart block used for cart tagging.
 */
class Knockout extends Template
{
    /** @var NostoHelperAccount  */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var NostoHelperScope  */
    private NostoHelperScope $nostoHelperScope;

    /** @var NostoHelperData  */
    private NostoHelperData $nostoHelperData;

    /** @var StoreManagerInterface */
    private StoreManagerInterface $storeManager;

    /** @var Logger */
    private Logger $logger;

    /**
     * Knockout constructor.
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperData $nostoHelperData
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoHelperData $nostoHelperData,
        Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $context->getStoreManager();
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperData = $nostoHelperData;
        $this->logger = $logger;
    }

    /**
     * Get relevant path to template
     *
     * @return string
     * @suppress PhanTypeMismatchReturn
     */
    public function getTemplate()
    {
        $template = null;
        if ($this->nostoEnabled()) {
            $template = parent::getTemplate();
        }

        return $template;
    }

    private function nostoEnabled()
    {
        $enabled = false;
        if ($this->nostoHelperAccount->nostoInstalledAndEnabled(
            $this->nostoHelperScope->getStore()
        )
        ) {
            $enabled = true;
        }

        return $enabled;
    }

    /**
     * Retrieve serialized JS layout configuration ready to use in template
     *
     * @return string
     */
    public function getJsLayout()
    {
        $jsLayout = '';
        if ($this->nostoEnabled()) {
            $jsLayout = parent::getJsLayout();
        }

        return $jsLayout;
    }

    /**
     * @return bool
     */
    public function isHyva()
    {
        try {
            return $this->nostoHelperScope->isHyvaEnabled($this->storeManager->getStore());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return int
     */
    public function isReloadRecsAfterAtcEnabled()
    {
        $reload = 0;
        try {
            $store = $this->storeManager->getStore();
            $reload = $this->nostoHelperData->isReloadRecsAfterAtcEnabled($store);
        } catch (Exception $e) {
            $this->logger->debug("Could not get value for reloading recs after ATC");
        } finally {
            return $reload;
        }
    }
}
