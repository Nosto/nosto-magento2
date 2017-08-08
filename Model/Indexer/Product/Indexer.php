<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Nosto\Tagging\Model\Indexer\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
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
    const HARD_LIMIT_FOR_PRODUCTS = 1000;
    const INDEXER_ID = 'nosto_product_sync';

    private $productService;
    private $productRepository;
    private $searchCriteriaBuilder;
    private $logger;

    /**
     * @param ProductService $productService
     * @param ProductRepository $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductService $productService,
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger

    ) {
        $this->productService = $productService;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;

        $this->logger->debug('Init Queue Indexer');
    }

    /**
     * @inheritdoc
     */
    public function executeFull()
    {
        $this->logger->debug('Execute FULL');

        // Fetch all enabled products
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('status', Status::STATUS_ENABLED, 'eq')
            ->setPageSize(self::HARD_LIMIT_FOR_PRODUCTS)
            ->setCurrentPage(1)
            ->create();
        $products = $this->productRepository->getList($searchCriteria);
        $this->productService->update($products->getItems());
    }

    /**
     * @inheritdoc
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     */
    public function execute($ids)
    {
        $this->productService->updateByIds($ids);
    }
}