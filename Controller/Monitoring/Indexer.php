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

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\Store;
use Nosto\Tagging\Block\MonitoringIndexer;
use Nosto\Tagging\Helper\DebuggerCookie;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Model\Category\Builder as CategoryBuilder;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;

class Indexer extends DebuggerCookie implements ActionInterface
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

    /** @var CategoryRepositoryInterface $categoryRepository */
    private CategoryRepositoryInterface $categoryRepository;

    /** @var CategoryBuilder $categoryBuilder */
    private CategoryBuilder $categoryBuilder;

    /** @var ProductFactory $productFactory */
    private ProductFactory $productFactory;

    /** @var OrderFactory $orderFactory */
    private OrderFactory $orderFactory;

    /** @var CategoryFactory $categoryFactory */
    private CategoryFactory $categoryFactory;

    /**
     * Indexer constructor
     *
     * @param PageFactory $pageFactory
     * @param RequestInterface $request
     * @param ProductRepositoryInterface $productRepository
     * @param Scope $scope
     * @param ProductBuilder $productBuilder
     * @param MonitoringIndexer $block
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderBuilder $orderBuilder
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryBuilder $categoryBuilder
     * @param CookieManagerInterface $cookieManager
     * @param ProductFactory $productFactory
     * @param OrderFactory $orderFactory
     * @param CategoryFactory $categoryFactory
     */
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
        RedirectFactory $redirectFactory,
        CategoryRepositoryInterface $categoryRepository,
        CategoryBuilder $categoryBuilder,
        CookieManagerInterface $cookieManager,
        ProductFactory $productFactory,
        OrderFactory $orderFactory,
        CategoryFactory $categoryFactory
    ) {
        parent::__construct($cookieManager);
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
        $this->categoryRepository = $categoryRepository;
        $this->categoryBuilder = $categoryBuilder;
        $this->productFactory = $productFactory;
        $this->orderFactory = $orderFactory;
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * Build Nosto entity based on wanted type
     *
     * @throws NoSuchEntityException
     */
    public function execute(): ResultInterface
    {
        if (false === $this->checkIfNostoDebuggerCookieExists()) {
            $this->messageManager->addErrorMessage('Please login to continue!');

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
        }

        $store = $this->scope->getStore();

        if ('product' === $this->request->getParam('entity_type')) {
            $this->buildNostoProduct($store, $this->request->getParam('entity_id'));
        }

        if ('order' === $this->request->getParam('entity_type')) {
            $this->buildNostoOrder($this->request->getParam('entity_id'));
        }

        if ('category' === $this->request->getParam('entity_type')) {
            $this->buildNostoCategory($store, $this->request->getParam('entity_id'));
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set('Nosto Debugger');

        return $page;
    }

    /**
     * Build Nosto product
     *
     * @param Store $store
     * @param $entityId
     * @return void
     * @throws NoSuchEntityException
     * @phan-suppress PhanTypeMismatchArgument
     */
    private function buildNostoProduct(Store $store, $entityId): void
    {
        /** @var Product $product */
        $product = $this->productRepository->getById($entityId);
        $nostoProduct = $this->productBuilder->build($product, $store);
        $this->block->setNostoProduct($nostoProduct);
        $this->block->setEntityId($product->getId());
        $this->block->setEntityType('product');
    }

    /**
     * Build Nosto order
     *
     * @param $entityId
     * @return void
     */
    private function buildNostoOrder($entityId): void
    {
        $order = $this->orderRepository->get($entityId);
        $orderModel = $this->orderFactory->create();
        $orderModel->setData($order->getData());
        $nostoOrder = $this->orderBuilder->build($orderModel);
        $this->block->setNostoOrder($nostoOrder);
        $this->block->setEntityId($entityId);
        $this->block->setEntityType('order');
    }

    /**
     * Build Nosto category
     *
     * @param Store $store
     * @param $entityId
     * @return void
     * @throws NoSuchEntityException
     */
    private function buildNostoCategory(Store $store, $entityId): void
    {
        $category = $this->categoryRepository->get($entityId);
        $categoryModel = $this->categoryFactory->create();
        $categoryModel->setData($category->getData());
        $nostoCategory = $this->categoryBuilder->build($categoryModel, $store);
        $this->block->setNostoCategory($nostoCategory);
        $this->block->setEntityId($category->getId());
        $this->block->setEntityType('category');
    }
}
