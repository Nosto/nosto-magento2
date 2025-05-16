<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Nosto\Tagging\Block\MonitoringProduct;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Model\Product\Builder;

class Product implements ActionInterface
{
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

    /** @var MonitoringProduct $block */
    private MonitoringProduct $block;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        RequestInterface $request,
        Scope $scope,
        Builder $builder,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        PageFactory $pageFactory,
        MonitoringProduct $block
    ) {
        $this->productRepository = $productRepository;
        $this->request = $request;
        $this->scope = $scope;
        $this->builder = $builder;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->pageFactory = $pageFactory;
        $this->block = $block;
    }

    public function execute()
    {
        $productId = $this->request->getParam('product_id');
        /** @var \Magento\Catalog\Model\Product $product */
        try {
            $product = $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
        }
        $store = $this->scope->getStore();
        $nostoProduct = $this->builder->build($product, $store);
        $this->block->setNostoProduct($nostoProduct);

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set('Nosto Debugger');

        return $page;
    }
}