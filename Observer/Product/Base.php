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

namespace Nosto\Tagging\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Module\Manager as ModuleManager;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Indexer\Product\Indexer;
use Nosto\Tagging\Model\Product\Service as NostoProductService;

abstract class Base implements ObserverInterface
{
    public $moduleManager;
    public $productService;
    public $productRepository;
    public $dataHelper;
    public $indexer;

    /**
     * Constructor.
     *
     * @param ModuleManager $moduleManager
     * @param NostoProductService $productService
     * @param ProductRepository $productRepository
     * @param NostoHelperData $dataHelper
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        ModuleManager $moduleManager,
        NostoProductService $productService,
        ProductRepository $productRepository,
        NostoHelperData $dataHelper,
        IndexerRegistry $indexerRegistry
    ) {
        $this->productService = $productService;
        $this->moduleManager = $moduleManager;
        $this->productRepository = $productRepository;
        $this->dataHelper = $dataHelper;
        $this->indexer = $indexerRegistry->get(Indexer::INDEXER_ID);
    }

    /**
     * Event handler for the "catalog_product_save_after" and  event.
     * Sends a product update API call to Nosto.
     *
     * @param Observer $observer
     * @return void
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        if ($this->moduleManager->isEnabled(NostoHelperData::MODULE_NAME)
            && !$this->indexer->isScheduled()
        ) {
            /* @var \Magento\Catalog\Model\Product $product */
            $product = $this->extractProduct($observer);

            if ($product instanceof Product && $product->getId()) {
                $this->productService->update([$product]);
            }
        }
    }

    /**
     * Default method for extracting product from the observer
     * @param Observer $observer
     * @return mixed
     */
    public function extractProduct(Observer $observer)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        return $observer->getProduct();
    }
}
