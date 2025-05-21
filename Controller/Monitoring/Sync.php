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

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Order\OrderCreate as NostoOrderCreate;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Request\Http\Exception\HttpResponseException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as CollectionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Model\Service\Sync\Upsert\Product\SyncService;

class Sync implements ActionInterface
{
    private const string CUSTOMER_ID = '2c_cId';

    /** @var RequestInterface $request */
    private RequestInterface $request;

    /** @var CollectionFactory $collectionFactory */
    private CollectionFactory $collectionFactory;

    /** @var SyncService $syncService */
    private SyncService $syncService;

    /** @var Scope $scope */
    private Scope $scope;

    /** @var ProductIndexer $productIndexer */
    private ProductIndexer $productIndexer;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var NostoHelperAccount $nostoHelperAccount */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var OrderRepositoryInterface $orderRepository */
    private OrderRepositoryInterface $orderRepository;

    /** @var OrderBuilder $orderBuilder */
    private OrderBuilder $orderBuilder;

    /** @var NostoHelperUrl $nostoHelperUrl */
    private NostoHelperUrl $nostoHelperUrl;

    /** @var CookieManagerInterface $cookieManager */
    private CookieManagerInterface $cookieManager;

    /** @var CategoryRepositoryInterface $categoryRepository */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * Sync constructor
     *
     * @param RequestInterface $request
     * @param CollectionFactory $collectionFactory
     * @param SyncService $syncService
     * @param Scope $scope
     * @param ProductIndexer $productIndexer
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $redirectFactory
     * @param NostoHelperAccount $nostoHelperAccount
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderBuilder $orderBuilder
     * @param NostoHelperUrl $nostoHelperUrl
     * @param CookieManagerInterface $cookieManager
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(
        RequestInterface $request,
        CollectionFactory $collectionFactory,
        SyncService $syncService,
        Scope $scope,
        ProductIndexer $productIndexer,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        NostoHelperAccount $nostoHelperAccount,
        OrderRepositoryInterface $orderRepository,
        OrderBuilder $orderBuilder,
        NostoHelperUrl $nostoHelperUrl,
        CookieManagerInterface $cookieManager,
        CategoryRepositoryInterface $categoryRepository
    ) {
        $this->request = $request;
        $this->collectionFactory = $collectionFactory;
        $this->syncService = $syncService;
        $this->scope = $scope;
        $this->productIndexer = $productIndexer;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->orderRepository = $orderRepository;
        $this->orderBuilder = $orderBuilder;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->cookieManager = $cookieManager;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @throws Exception
     */
    public function execute(): Redirect
    {
        $store = $this->scope->getStore();

        if ('product' === $this->request->getParam('entity_type')) {
            try {
                $this->productSync($store, $this->request->getParam('entity_id'));

                $this->messageManager->addSuccessMessage('Product successfully synced.');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        if ('order' === $this->request->getParam('entity_type')) {
            try {
                $this->orderSync($store, $this->request->getParam('entity_id'));

                $this->messageManager->addSuccessMessage('Order successfully synced.');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        if ('category' === $this->request->getParam('entity_type')) {
            try {
                $this->categorySync($store, $this->request->getParam('entity_id'));

                $this->messageManager->addSuccessMessage('Category successfully synced.');
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->redirectFactory->create()
            ->setUrl(
                '/nosto/monitoring/indexer?entity_type='
                .$this->request->getParam('entity_type')
                .'&entity_id='.$this->request->getParam('entity_id')
            );
    }

    /**
     * Sync product
     *
     * @param Store $store
     * @param string $entityId
     * @return void
     * @throws AbstractHttpException
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     * @throws Exception
     */
    private function productSync(Store $store, string $entityId): void
    {
        $product = $this->collectionFactory->create();
        $product->addAttributeToFilter('entity_id', ['eq' => $entityId]);
        $this->productIndexer->executeRow($product->getFirstItem()->getData('entity_id'));
        $this->productIndexer->doIndex($store, [$product->getFirstItem()->getData('entity_id')]);
        $this->syncService->sync($product, $store);
    }

    /**
     * Sync order
     *
     * @param Store $store
     * @param string $entityId
     * @return void
     * @throws AbstractHttpException
     * @throws NostoException
     * @throws HttpResponseException
     */
    private function orderSync(Store $store, string $entityId): void
    {
        $account = $this->nostoHelperAccount->findAccount($store);
        /** @var Order $order */
        $order = $this->orderRepository->get($entityId);
        $nostoOrder = $this->orderBuilder->build($order);
        $customerId = $this->cookieManager->getCookie(self::CUSTOMER_ID);
        $orderService = new NostoOrderCreate(
            $nostoOrder,
            $account,
            AbstractGraphQLOperation::IDENTIFIER_BY_CID,
            $customerId,
            $this->nostoHelperUrl->getActiveDomain($store)
        );
        $orderService->execute();
    }

    /**
     * Sync category
     *
     * @param Store $store
     * @param string $entityId
     * @return void
     * @throws AbstractHttpException
     * @throws MemoryOutOfBoundsException
     * @throws NostoException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    private function categorySync(Store $store, string $entityId): void
    {
        /** @var Category $category */
        $category = $this->categoryRepository->get($entityId);
        $products = $this->collectionFactory->create();
        $products->addCategoryFilter($category);
        $this->productIndexer->execute($products->getAllIds());
        $this->productIndexer->doIndex($store, [$products->getAllIds()]);
        $this->syncService->sync($products, $store);
    }
}
