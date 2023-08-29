<?php
/**
 * Copyright (c) 2023, Nosto Solutions Ltd
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

namespace Nosto\Tagging\Observer\Category;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Model\Store;
use Nosto\Operation\Category\CategoryUpdate as NostoCategoryUpdate;
use Nosto\Request\Http\HttpRequest;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Types\Signup\AccountInterface;

class Update implements ObserverInterface
{
    private NostoHelperData $nostoHelperData;
    private NostoHelperAccount $nostoHelperAccount;
    private NostoCategoryBuilder $nostoCategoryBuilder;
    private NostoLogger $logger;
    private ModuleManager $moduleManager;
    private NostoHelperUrl $nostoHelperUrl;

    /**
     * Save constructor.
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoCategoryBuilder $nostoCategoryBuilder
     * @param NostoLogger $logger
     * @param ModuleManager $moduleManager
     * @param NostoHelperUrl $nostoHelperUrl
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoCategoryBuilder $nostoCategoryBuilder,
        NostoLogger $logger,
        ModuleManager $moduleManager,
        NostoHelperUrl $nostoHelperUrl
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoCategoryBuilder = $nostoCategoryBuilder;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->nostoHelperUrl = $nostoHelperUrl;
    }

    /**
     * Event handler for the "catalog_category_save_after" and  event.
     * Sends a category update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @suppress PhanDeprecatedFunction
     * @suppress PhanTypeMismatchArgument
     */
    public function execute(Observer $observer)
    {
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)) {
            HttpRequest::buildUserAgent(
                'Magento',
                $this->nostoHelperData->getPlatformVersion(),
                $this->nostoHelperData->getModuleVersion()
            );

            /* @var Category $category */
            /** @noinspection PhpUndefinedMethodInspection */
            $category = $observer->getCategory();

            $store = $category->getStore();
            $nostoAccount = $this->nostoHelperAccount->findAccount(
                $store
            );
            if ($nostoAccount !== null) {
                $this->updateCategory($category, $nostoAccount, $store);
            }
        }
    }

    /**
     * Send a category update to Nosto
     *
     * @param Category $category
     * @param AccountInterface $nostoAccount
     * @param Store $store
     */
    private function updateCategory(Category $category, AccountInterface $nostoAccount, Store $store)
    {
        $nostoCategory = $this->nostoCategoryBuilder->build($category, $store);
        try {
            $categoryService = new NostoCategoryUpdate(
                $nostoCategory,
                $nostoAccount,
                $this->nostoHelperUrl->getActiveDomain($store)
            );
            $categoryService->execute();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf(
                    'Failed to update categoeries with id %s.
                        Message was: %s',
                    $category->getId(),
                    $e->getMessage()
                )
            );
        }
    }
}
