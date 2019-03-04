<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\QueueRepository as NostoQueueRepository;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Magento\Framework\Phrase;
use Magento\Framework\Exception\LocalizedException;

/**
 * An indexer for Nosto product sync
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const BATCH_SIZE = 1000;
    const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $dataHelper;
    private $logger;
    private $productCollectionFactory;
    private $nostoQueueRepository;
    private $nostoHelperAccount;

    /**
     * @param ProductService $productService
     * @param NostoHelperData $dataHelper
     * @param NostoLogger $logger
     * @param ProductCollectionFactory $productCollectionFactory
     * @param NostoQueueRepository $nostoQueueRepository
     * @param NostoHelperAccount\Proxy $nostoHelperAccount
     */
    public function __construct(
        ProductService $productService,
        NostoHelperData $dataHelper,
        NostoLogger $logger,
        ProductCollectionFactory $productCollectionFactory,
        NostoQueueRepository $nostoQueueRepository,
        NostoHelperAccount\Proxy $nostoHelperAccount
    ) {
        $this->productService = $productService;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->nostoQueueRepository = $nostoQueueRepository;
        $this->nostoHelperAccount = $nostoHelperAccount;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function executeFull()
    {
        if (!$this->dataHelper->isFullReindexEnabled()
            || empty($this->nostoHelperAccount->getStoresWithNosto())
        ) {
            $this->logger->info(
                'Skip full reindex since full reindex is disabled or Nosto account is not connected.'
            );
            return;
        }
        // Truncate queue table before first execution if they have leftover products
        if ($this->nostoQueueRepository->isQueuePopulated()) {
            $this->nostoQueueRepository->truncate();
        }
        $productCollection = $this->getProductCollection();
        $productCollection->setPageSize(self::BATCH_SIZE);
        $lastPage = $productCollection->getLastPageNumber();
        $pageNumber = 1;
        do {
            $productCollection->setCurPage($pageNumber);
            $productCollection->addAttributeToSelect('id')
                ->addAttributeToFilter(
                    'status',
                    ['eq'=> Status::STATUS_ENABLED]
                )->addAttributeToFilter(
                    'visibility',
                    ['neq'=> Visibility::VISIBILITY_NOT_VISIBLE]
                );
            $products = [];
            foreach ($productCollection->getItems() as $product) {
                $products[$product->getId()] = $product->getTypeId();
            }
            $productCollection->clear();
            $this->logger->logWithMemoryConsumption(
                sprintf('Indexing from executeFull, remaining pages: %d', $lastPage - $pageNumber)
            );
            try {
                $this->productService->update($products);
            } catch (MemoryOutOfBoundsException $e) {
                throw new LocalizedException(new Phrase($e->getMessage()));
            }
            $this->productService->processed = [];
            $products = null;
            $pageNumber++;
        } while ($pageNumber <= $lastPage);
        $productCollection = null;
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
        if (empty($this->nostoHelperAccount->getStoresWithNosto())) {
            $this->logger->info(
                'Nosto account is not connected. Skipping reindex.'
            );
            return;
        }
        $this->logger->logWithMemoryConsumption(sprintf('Got %d ids from CL', count($ids)));
        $splitted = array_chunk(array_unique($ids), self::BATCH_SIZE);
        foreach ($splitted as $batch) {
            $this->productService->updateByIds($batch);
        }
    }

    /**
     * @return ProductCollection
     */
    private function getProductCollection()
    {
        return $this->productCollectionFactory->create();
    }
}
