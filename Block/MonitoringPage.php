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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Indexer\Model\ResourceModel\Indexer\State\Collection;
use Magento\Store\Model\Store;
use Nosto\Model\Signup\Account as NostoAccount;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Helper\Scope;

class MonitoringPage extends Template
{
    private const ENABLED = 'Yes';

    private const DISABLED = 'No';

    /** @var Account $account */
    private Account $account;

    /** @var Store $store */
    private Store $store;

    /** @var Data $settings */
    private Data $settings;

    /**
     * MonitoringPage constructor
     *
     * @param Context $context
     * @param Account $account
     * @param Scope $scope
     * @param Data $settings
     * @param array $data
     */
    public function __construct(
        Context $context,
        Account $account,
        Scope $scope,
        Data $settings,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->account = $account;
        $this->store = $scope->getStore();
        $this->settings = $settings;
    }

    /**
     * Return form action
     *
     * @param string $url
     * @return string
     */
    public function getFormAction(string $url): string
    {
        return $this->getUrl($url, ['_secure' => true]);
    }

    /**
     * Check if Nosto in installed and enabled in store
     *
     * @return string
     */
    public function checkIfNostoInstalledAndEnabled(): string
    {
        if (true === $this->account->nostoInstalledAndEnabled($this->store)) {
            return self::ENABLED;
        }

        return self::DISABLED;
    }

    /**
     * Get account name and account tokens
     *
     * @return NostoAccount
     */
    public function getAccountNameAndTokens(): NostoAccount
    {
        return $this->account->findAccount($this->store);
    }

    /**
     * Returns an array of stores where Nosto is installed
     *
     * @return array
     */
    public function getStoresWithNosto(): array
    {
        return $this->account->getStoresWithNosto();
    }

    /**
     * Returns different types of Nosto settings in Magento admin
     *
     * @param string $method
     * @return string
     */
    public function getSettingsValue(string $method): string
    {
        if (true === $this->settings->$method($this->store) || '1' === $this->settings->$method($this->store)) {
            return self::ENABLED;
        }

        return self::DISABLED;
    }

    /**
     * Returns different types of Nosto settings in Magento admin
     *
     * @param string $method
     * @return string
     */
    public function getStringSettingsValues(string $method): string
    {
        return $this->settings->$method($this->store);
    }

    /**
     * Returns last indexed time
     *
     * @param string $indexerName
     * @return array
     */
    public function getLastIndexedTime(string $indexerName): array
    {
        $indexerCollection = ObjectManager::getInstance()
            ->create(Collection::class);
        $nostoIndexer = $indexerCollection->getItemByColumnValue('indexer_id', $indexerName);

        return [
            'indexer_id' => $nostoIndexer->getData('indexer_id'),
            'updated' => $nostoIndexer->getData('updated')
        ];
    }
}
