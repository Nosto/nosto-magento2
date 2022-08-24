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

namespace Nosto\Tagging\Block\Adminhtml\Account;

use Magento\Backend\Block\Template as BlockTemplate;
use Magento\Backend\Block\Template\Context as BlockContext;
use Magento\Framework\Escaper;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Framework\Exception\NotFoundException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger;

/**
 * Block for displaying the Nosto account management controls.
 * This block is used to setup and manage your Nosto accounts on a store basis
 * in Magento.
 */
class Connection extends BlockTemplate
{
    private Escaper $escaper;
    private BackendHelper $backendHelper;
    private NostoHelperAccount $nostoHelperAccount;
    private NostoHelperScope $nostoHelperScope;
    private Logger $logger;

    /**
     * Constructor.
     *
     * @param BlockContext $context the context.
     * @param BackendHelper $backendHelper
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param Logger $logger
     * @param array $data
     */
    public function __construct(
        BlockContext $context,
        BackendHelper $backendHelper,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->escaper = $context->getEscaper();
        $this->backendHelper = $backendHelper;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->logger = $logger;
    }

    /**
     * Checks if Nosto module is enabled and Nosto account is set
     *
     * @return bool
     */
    public function nostoInstalledAndEnabled()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->nostoHelperAccount->nostoInstalledAndEnabled($store);
        } catch (NotFoundException $e) {
            $this->logger->exception($e);
        }

        return false;
    }

    /**
     * Get Nosto account name to display on the admin view.
     *
     * @return string
     */
    public function getAccountName()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            $account = $this->nostoHelperAccount->findAccount($store);
            return $account->getName();
        } catch (NotFoundException $e) {
            $this->logger->exception($e);
        }

        return '';
    }

    /**
     * Returns the Nosto open url
     *
     * @return string url to the Open controller
     */
    public function getNostoUrl()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->backendHelper->getUrl('*/*/open', ['store' => $store->getId()]);
        } catch (NotFoundException $e) {
            $this->logger->exception($e);
        }

        return '';
    }

    /**
     * Checks if there are missing Nosto tokens
     *
     * @return bool true if some token(s) are missing, false otherwise.
     */
    public function hasMissingTokens()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            $account = $this->nostoHelperAccount->findAccount($store);
            return $account->hasMissingTokens();
        } catch (NotFoundException $e) {
            $this->logger->exception($e);
        }

        return false;
    }

    /**
     * Returns the Nosto account deletion url.
     *
     * @return string Nosto account deletion url.
     */
    public function getAccountDeleteUrl()
    {
        try {
            $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());
            return $this->backendHelper->getUrl('*/*/delete', ['store' => $store->getId()]);
        } catch (NotFoundException $e) {
            $this->logger->exception($e);
        }

        return '';
    }

    /**
     * @return Escaper
     */
    public function getEscaper()
    {
        return $this->escaper;
    }
}
