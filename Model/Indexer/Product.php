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

namespace Nosto\Tagging\Model\Indexer;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Nosto;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\QueueRepository as NostoQueueRepository;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as IndexCollection;
use Nosto\Tagging\Model\ResourceModel\Product\Index\CollectionFactory as IndexCollectionFactory;
use Nosto\Tagging\Model\Product\Index\Index as NostoIndex;

/**
 * An indexer for Nosto product sync
 */
class Product implements IndexerActionInterface, MviewActionInterface
{
    const BATCH_SIZE = 1000;
    const INDEXER_ID = 'nosto_product_index';

    /** @var ProductService  */
    private $productService;

    /** @var NostoHelperData  */
    private $dataHelper;

    /** @var NostoLogger  */
    private $logger;

    /** @var ProductCollectionFactory  */
    private $productCollectionFactory;

    /** @var NostoQueueRepository  */
    private $nostoQueueRepository;

    /** @var NostoHelperAccount\Proxy  */
    private $nostoHelperAccount;

    /** @var NostoProductBuilder  */
    private $nostoProductBuilder;

    /** @var NostoIndexService */
    private $nostoServiceIndex;

    /** @var IndexCollectionFactory */
    private $indexCollectionFactory;

    /**
     * Product constructor.
     * @param ProductService $productService
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     * @param NostoQueueRepository $nostoQueueRepository
     * @param NostoHelperAccount\Proxy $nostoHelperAccount
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoIndexService $nostoServiceIndex
     * @param IndexCollectionFactory $indexCollectionFactory
     */
    public function __construct(ProductService $productService, NostoHelperData $dataHelper, NostoLogger $logger, ProductCollectionFactory $productCollectionFactory, NostoQueueRepository $nostoQueueRepository, NostoHelperAccount\Proxy $nostoHelperAccount, NostoProductBuilder $nostoProductBuilder, NostoIndexService $nostoServiceIndex, IndexCollectionFactory $indexCollectionFactory)
    {
        $this->productService = $productService;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->nostoQueueRepository = $nostoQueueRepository;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->indexCollectionFactory = $indexCollectionFactory;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        $indexCollection = $this->getIndexCollection();
        $indexCollection->setPageSize(self::BATCH_SIZE);
        $lastPage = $indexCollection->getLastPageNumber();
        $page = 1;
        while ($page <= $lastPage) {
            $indexCollection->setCurPage($page);
            $indexCollection->addAttributeToSelect(NostoIndex::ID)
                ->addAttributeToFilter(NostoIndex::IS_DIRTY ,['eq' => NostoIndex::VALUE_IS_DIRTY]);

            foreach ($indexCollection->getItems() as $indexedProduct) {
                $this->nostoServiceIndex->handleDirtyProduct($indexedProduct[0]);
            }
            $page++;
        }
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function execute($ids)
    {
        $storesWithNosto = $this->nostoHelperAccount->getStoresWithNosto();
        if (!empty($storesWithNosto)) {
            foreach ($ids as $id) {
                $this->nostoServiceIndex->handleDirtyProduct($id);
            }
        } else {
            $this->logger->info(
                'Nosto account is not connected into any store view. Nothing to index.'
            );
        }
    }

    /**
     * @return IndexCollection
     */
    private function getIndexCollection()
    {
        return $this->indexCollectionFactory->create();
    }
}
