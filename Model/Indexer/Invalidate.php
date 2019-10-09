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

use Magento\Indexer\Model\ProcessManager;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Indexer\Dimensions\Invalidate\ModeSwitcher as InvalidateModeSwitcher;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Nosto\Tagging\Model\Indexer\Dimensions\StoreDimensionProvider;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\Collection as ProductCollection;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionBuilder;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as ProductCollectionFactory;
use Nosto\Tagging\Model\Service\Cache\CacheService;
use Nosto\Tagging\Model\Service\Indexer\IndexerStatusServiceInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class Invalidate
 * This class is responsible for listening to product changes
 * and setting the `is_dirty` value in `nosto_product_index` table
 * @package Nosto\Tagging\Model\Indexer
 */
class Invalidate extends AbstractIndexer
{
    const INDEXER_ID = 'nosto_index_product_invalidate';

    /** @var CacheService */
    private $nostoServiceIndex;

    /** @var CollectionBuilder */
    private $productCollectionBuilder;

    /** @var InvalidateModeSwitcher */
    private $modeSwitcher;

    /**
     * Invalidate constructor.
     * @param NostoHelperScope $nostoHelperScope
     * @param CacheService $nostoCacheService
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionBuilder
     * @param InvalidateModeSwitcher $modeSwitcher
     * @param StoreDimensionProvider $dimensionProvider
     * @param Emulation $storeEmulation
     * @param ProcessManager $processManager
     * @param InputInterface $input
     * @param IndexerStatusServiceInterface $indexerStatusService
     */
    public function __construct(
        NostoHelperScope $nostoHelperScope,
        CacheService $nostoCacheService,
        NostoLogger $logger,
        CollectionBuilder $productCollectionBuilder,
        InvalidateModeSwitcher $modeSwitcher,
        StoreDimensionProvider $dimensionProvider,
        Emulation $storeEmulation,
        ProcessManager $processManager,
        InputInterface $input,
        IndexerStatusServiceInterface $indexerStatusService
    ) {
        $this->nostoServiceIndex = $nostoCacheService;
        $this->productCollectionBuilder = $productCollectionBuilder;
        $this->modeSwitcher = $modeSwitcher;
        parent::__construct(
            $nostoHelperScope,
            $logger,
            $dimensionProvider,
            $storeEmulation,
            $input,
            $indexerStatusService,
            $processManager
        );
    }

    /**
     * @inheritdoc
     */
    public function getModeSwitcher(): ModeSwitcherInterface
    {
        return $this->modeSwitcher;
    }

    /**
     * @inheritdoc
     * @throws NostoException
     */
    public function doIndex(Store $store, array $ids = [])
    {
        $productCollection = $this->getCollection($store, $ids);
        $this->nostoServiceIndex->invalidateOrCreate($productCollection, $store);
        if (!empty($ids)) {
            //In case for this specific set of ids
            //there are more entries of products in the indexer table than the magento product collection
            //it means that some products were deleted
            $ids = array_unique($ids);
            $idsSize = count($ids);
            $collectionSize = $productCollection->getSize();
            if ($idsSize > $collectionSize) {
                $this->nostoServiceIndex->markProductsAsDeletedByDiff($productCollection, $ids, $store);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getIndexerId(): string
    {
        return self::INDEXER_ID;
    }

    /**
     * @param Store $store
     * @param array $ids
     * @return ProductCollection
     */
    public function getCollection(Store $store, array $ids = [])
    {
        $this->productCollectionBuilder->initDefault($store);
        if (!empty($ids)) {
            $this->productCollectionBuilder->withIds($ids);
        } else {
            $this->productCollectionBuilder->withDefaultVisibility();
        }
        return $this->productCollectionBuilder->build();
    }
}
