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

    /**
     * Debug constructor
     *
     * @param ProductRepositoryInterface $productRepository
     * @param RequestInterface $request
     * @param Scope $scope
     * @param Builder $builder
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param PageFactory $pageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CategoryRepositoryInterface $categoryRepository
     */
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

    /**
     * Redirects to wanted type of indexer
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $entityType = $this->request->getParam('entityType');
        if (self::PRODUCT_ENTITY === $entityType) {
            $productId = $this->request->getParam('product_id');

            return $this->getEntityIfExistAndRedirectToIndexerPage('productRepository', $productId, 'getById', $entityType);
        }
        if (self::ORDER_ENTITY === $entityType) {
            $orderId = $this->request->getParam('order_id');

            return $this->getEntityIfExistAndRedirectToIndexerPage('orderRepository', $orderId, 'get', $entityType);
        }
        if (self::CATEGORY_ENTITY === $entityType) {
            $categoryId = $this->request->getParam('category_id');

            return $this->getEntityIfExistAndRedirectToIndexerPage('categoryRepository', $categoryId, 'get', $entityType);
        }

        $this->messageManager->addErrorMessage(__('Invalid entity type.'));

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/index');
    }

    /**
     * Get entity by entity type and redirect to indexer page if entity has been found
     *
     * @param $repository
     * @param $entityId
     * @param $method
     * @param $entityType
     * @return Redirect
     */
    private function getEntityIfExistAndRedirectToIndexerPage($repository, $entityId, $method, $entityType): Redirect
    {
        try {
            $entity = $this->$repository->$method($entityId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
        }

        return $this->redirectFactory->create()->setUrl(
            '/nosto/monitoring/indexer?entity_type='.$entityType.'&entity_id='.$entity->getId()
        );
    }
}