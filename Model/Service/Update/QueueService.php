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

namespace Nosto\Tagging\Model\Service\Update;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Queue\QueueBuilder;
use Nosto\Tagging\Model\Product\Queue\QueueRepository;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Util\PagingIterator;

/**
 * Class QueueService
 */
class QueueService extends AbstractService
{
    const PRODUCTID_BATCH_SIZE = 1000;
    const PRODUCT_DELETION_BATCH_SIZE = 1000;

    /** @var QueueRepository  */
    private $queueRepository;

    /** @var QueueBuilder */
    private $queueBuilder;

    /** @var NostoProductRepository $nostoProductRepository */
    private $nostoProductRepository;

    /**
     * CacheService constructor.
     * @param QueueRepository $queueRepository
     * @param QueueBuilder $queueBuilder
     * @param NostoLogger $logger
     * @param NostoDataHelper $nostoDataHelper
     */
    public function __construct(
        QueueRepository $queueRepository,
        QueueBuilder $queueBuilder,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper
    ) {
        parent::__construct($nostoDataHelper, $logger);
        $this->queueRepository = $queueRepository;
        $this->queueBuilder = $queueBuilder;
    }

    /**
     * Sets the products into the update queue
     *
     * @param ProductCollection $collection
     * @param Store $store
     * @throws NostoException
     * @throws Exception
     */
    public function addCollectionToQueue(ProductCollection $collection, Store $store)
    {
        $collection->setPageSize(self::PRODUCTID_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            $queueEntry = $this->queueBuilder->build(
                $store,
                $this->toParentProructIds($page)
            );
            if (count($queueEntry->getProductIds()) > 0) {
                $this->queueRepository->save($queueEntry);
            }
        }
    }

    /**
     * @param ProductCollection $collection
     * @return array
     * @throws NostoException
     */
    private function toParentProructIds(ProductCollection $collection)
    {
        $productIds = [];
        /** @var ProductCollection $collection */
        $collection->setPageSize(self::PRODUCTID_BATCH_SIZE);
        $iterator = new PagingIterator($collection);

        /** @var ProductCollection $page */
        foreach ($iterator as $page) {
            /** @var ProductInterface $product */
            foreach ($page->getItems() as $product) {
                $parents = $this->nostoProductRepository->resolveParentProductIds($product);
                if (!empty($parents)) {
                    foreach ($parents as $id) {
                        $productIds[] = $id;
                    }
                } else {
                    $productIds[] = $product->getId();
                }
            }
        }
        return array_unique($productIds);
    }
}
