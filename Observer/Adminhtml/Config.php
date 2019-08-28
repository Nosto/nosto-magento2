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

namespace Nosto\Tagging\Observer\Adminhtml;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Magento\Store\Model\Store;

/**
 * Observer to mark all indexed products as dirty if settings have changed
 *
 * @package Nosto\Tagging\Observer\Adminhtml
 */
class Config implements ObserverInterface
{
    private $logger;
    private $moduleManager;
    private $nostoHelperScope;
    private $nostoAccountHelper;
    private $indexCollection;

    /**
     * Config Constructor.
     *
     * @param NostoLogger $logger
     * @param ModuleManager $moduleManager
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoAccountHelper $nostoAccountHelper
     * @param IndexCollection $indexCollection
     */
    public function __construct(
        NostoLogger $logger,
        ModuleManager $moduleManager,
        NostoHelperScope $nostoHelperScope,
        NostoAccountHelper $nostoAccountHelper,
        IndexCollection $indexCollection
    ) {
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoAccountHelper = $nostoAccountHelper;
        $this->indexCollection = $indexCollection;
    }

    /**
     * Observer method to mark all indexed products as dirty on the index table
     *
     * @param Observer $observer the dispatched event
     */
    public function execute(Observer $observer) // @codingStandardsIgnoreLine
    {
        $changedConfig = $observer->getData('changed_paths');
        if (empty($changedConfig)
            || !$this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)
            || in_array(NostoHelperData::XML_PATH_INDEXER_MEMORY, $changedConfig, false) // @todo: if it's the only thing in the array
        ) {
            return;
        }
        $storeRequest = $observer->getData('store');
        // If $storeRequest is empty string, means we're in a global scope
        // mark as dirty for all stores
        if (empty($storeRequest)) {
            $stores = $this->nostoHelperScope->getStores();
            foreach ($stores as $store) {
                $this->markAllAsDirtyByStore($store);
            }
        } else {
            $store = $this->nostoHelperScope->getStore($storeRequest);
            $this->markAllAsDirtyByStore($store);
        }
    }

    /** Wrapper to log and mark all products as dirty after configuration has changed
     * @param Store $store
     */
    private function markAllAsDirtyByStore(Store $store)
    {
        if ($this->nostoAccountHelper->nostoInstalledAndEnabled($store)) {
            $this->logger->info(
                sprintf(
                    'Nosto Settings updated, marking all indexed products as dirty for store %s',
                    $store->getName()
                )
            );
            $this->indexCollection->markAllAsDirtyByStore($store);
            $this->logger->info(
                sprintf(
                    'All indexed products were marked as dirty for store %s',
                    $store->getName()
                )
            );
        }
    }
}
