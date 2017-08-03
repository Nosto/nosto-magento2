<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Nosto\Tagging\Model\Indexer\Queue;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Nosto\Tagging\Model\Product\Service as ProductService;
use Psr\Log\LoggerInterface;

/**
 * An indexer for Nosto product sync
 *
 */
class Indexer implements IndexerActionInterface, MviewActionInterface
{
    const INDEXER_ID = 'nosto_queue_products';

    protected $productService;
    protected $productRepository;
    protected $logger;

    /**
     * @param ProductService $productService
     * @param ProductRepository $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductService $productService,
        ProductRepository $productRepository,
        LoggerInterface $logger

    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        $this->logger = $logger;

        $this->logger->debug('Init Queue Indexer');
    }

    public function executeFull()
    {
        $this->logger->debug('Exec full');
        $collection = $this->productRepository->create()
            ->addAttributeToFilter('status', ['eq' => '1'])
            ->addAttributeToSelect('*');
        $collection->setPage(1,10);

        $this->productService->update($collection);

    }

    public function executeList(array $ids)
    {
        $this->logger->debug('Exec list');

        $collection = $this->productRepository->create()
            ->addAttributeToFilter('status', ['eq' => '1'])
            ->addAttributeToSelect('*');
        $collection->setPage(1,10);

        $this->productService->update($collection);

    }

    public function executeRow($id)
    {
        $this->logger->debug('Exec row');
        $this->execute([$id]);
    }

    public function execute($ids)
    {
        $this->productService->addToQueueByIds($ids);
        $this->productService->flushQueue();
    }
}