<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Model\Product\Builder;

class Debug implements ActionInterface
{
    private const string PRODUCT_ENTITY = 'product';

    private const string ORDER_ENTITY = 'order';

    private const string CATEGORY_ENTITY = 'category';

    /** @var ProductRepositoryInterface $productRepository */
    private ProductRepositoryInterface $productRepository;

    /** @var RequestInterface $request */
    private RequestInterface $request;

    /** @var Scope $scope */
    private Scope $scope;

    /** @var Builder $builder */
    private Builder $builder;

    /** @var ManagerInterface $manager */
    private ManagerInterface $messageManager;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var PageFactory $pageFactory */
    private PageFactory $pageFactory;

    /** @var OrderRepositoryInterface $orderRepository */
    private OrderRepositoryInterface $orderRepository;

    /** @var CategoryRepositoryInterface $categoryRepository */
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        RequestInterface $request,
        Scope $scope,
        Builder $builder,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        PageFactory $pageFactory,
        OrderRepositoryInterface $orderRepository,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->request = $request;
        $this->scope = $scope;
        $this->builder = $builder;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->pageFactory = $pageFactory;
        $this->orderRepository = $orderRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public function execute()
    {
//        $categoryId = $this->request->getParam('category_id');
//        try {
//            $entity = $this->categoryRepository->get($categoryId);
//        } catch (NoSuchEntityException $e) {
//            $this->messageManager->addErrorMessage($e->getMessage());
//
//            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
//        }
//
//        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?category_id='.$entity->getId());

        $entityType = $this->request->getParam('entityType');
        switch ($entityType) {
            case self::PRODUCT_ENTITY:
                $productId = $this->request->getParam('product_id');

                return $this->getEntityIfExistAndRedirectToIndexerPage('productRepository', $productId, 'getById', $entityType);
            case self::ORDER_ENTITY:
                $orderId = $this->request->getParam('order_id');

                return $this->getEntityIfExistAndRedirectToIndexerPage('orderRepository', $orderId, 'get', $entityType);
            case self::CATEGORY_ENTITY:
                $categoryId = $this->request->getParam('category_id');

                return $this->getEntityIfExistAndRedirectToIndexerPage('categoryRepository', $categoryId, 'get', $entityType);
        }

//        if (self::PRODUCT_ENTITY == $type) {
//            $productId = $this->request->getParam('product_id');
//
//            return $this->getEntityIfExistAndRedirectToIndexerPage('productRepository', $productId, 'getById', $type);
//
////            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?product_id=' . $product->getId());
//        }
//
//        if (self::ORDER_ENTITY == $type) {
//            $orderId = $this->request->getParam('order_id');
//
//            return $this->getEntityIfExistAndRedirectToIndexerPage('orderRepository', $orderId, 'get', $type);
//
////            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?product_id=' . $product->getId());
//        }

//        try {
//            $product = $this->productRepository->getById($productId);
//        } catch (NoSuchEntityException $e) {
//            $this->messageManager->addErrorMessage($e->getMessage());
//
//            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
//        }
//
//        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?product_id=' . $product->getId());
//        $store = $this->scope->getStore();
//        $nostoProduct = $this->builder->build($product, $store);
//        $this->block->setNostoProduct($nostoProduct);
//
//        $page = $this->pageFactory->create();
//        $page->getConfig()->getTitle()->set('Nosto Debugger');
//
//        return $page;
    }

    private function getEntityIfExistAndRedirectToIndexerPage($repository, $entityId, $method, $entityType): Redirect
    {
        try {
            $entity = $this->$repository->$method($entityId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
        }

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?entity_type='.$entityType.'&entity_id='.$entity->getId());
    }
}