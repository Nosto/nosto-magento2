<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Nosto\Tagging\Block\MonitoringIndexer;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;

class Indexer implements ActionInterface
{
    /** @var PageFactory $pageFactory */
    private PageFactory $pageFactory;

    /** @var RequestInterface $request */
    private RequestInterface $request;

    /** @var ProductRepositoryInterface $productRepository */
    private ProductRepositoryInterface $productRepository;

    /** @var Scope $ */
    private Scope $scope;

    /** @var ProductBuilder $productBuilder */
    private ProductBuilder $productBuilder;

    /** @var MonitoringIndexer $block */
    private MonitoringIndexer $block;

    /** @var OrderRepositoryInterface $orderRepository */
    private OrderRepositoryInterface $orderRepository;

    /** @var OrderBuilder $orderBuilder */
    private OrderBuilder $orderBuilder;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    public function __construct(
        PageFactory $pageFactory,
        RequestInterface $request,
        ProductRepositoryInterface $productRepository,
        Scope $scope,
        ProductBuilder $productBuilder,
        MonitoringIndexer $block,
        OrderRepositoryInterface $orderRepository,
        OrderBuilder $orderBuilder,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->request = $request;
        $this->productRepository = $productRepository;
        $this->scope = $scope;
        $this->productBuilder = $productBuilder;
        $this->block = $block;
        $this->orderRepository = $orderRepository;
        $this->orderBuilder = $orderBuilder;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function execute()
    {
//        switch ($this->request->getParam('entity_type')) {
//            case 'product':
//                /** @var Product $product */
//                $product = $this->productRepository->getById($this->request->getParam('entity_id'));
//                $store = $this->scope->getStore();
//                $nostoProduct = $this->productBuilder->build($product, $store);
//                $this->block->setNostoProduct($nostoProduct);
//            case 'order':
//                /** @var Order $order */
//                $order = $this->orderRepository->get($this->request->getParam('entity_id'));
//                $nostoOrder = $this->orderBuilder->build($order);
//                $this->block->setNostoOrder($nostoOrder);
//        }

        if (!isset($_SESSION['nosto_debbuger_session'])) {
            $this->messageManager->addErrorMessage('Please login to continue!');

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
        }

        if ('product' === $this->request->getParam('entity_type')) {
            /** @var Product $product */
            $product = $this->productRepository->getById($this->request->getParam('entity_id'));
            $store = $this->scope->getStore();
            $nostoProduct = $this->productBuilder->build($product, $store);
            $this->block->setNostoProduct($nostoProduct);
        }

        if ('order' === $this->request->getParam('entity_type')) {
            /** @var Order $order */
            $order = $this->orderRepository->get($this->request->getParam('entity_id'));
            $nostoOrder = $this->orderBuilder->build($order);
            $this->block->setNostoOrder($nostoOrder);
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set('Nosto Debugger');

        return $page;
    }
}