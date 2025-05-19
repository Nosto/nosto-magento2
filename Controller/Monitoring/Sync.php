<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Nosto\DelayedOrders\Operation\NewSession;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\Model\Signup\Account;
use Nosto\NostoException;
use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Operation\Order\OrderCreate as NostoOrderCreate;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Indexer\CategoryIndexer;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionFactory as CategoryCollectionFactory;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as CollectionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Nosto\Tagging\Helper\Scope;
use Nosto\Tagging\Model\Service\Sync\Upsert\Category\SyncService as CategorySyncService;
use Nosto\Tagging\Model\Service\Sync\Upsert\Product\SyncService;

class Sync implements ActionInterface
{
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

    /** @var Logger $logger */
    private Logger $logger;

    /** @var OrderRepositoryInterface $orderRepository */
    private OrderRepositoryInterface $orderRepository;

    /** @var OrderBuilder $orderBuilder */
    private OrderBuilder $orderBuilder;

    /** @var NostoHelperUrl $nostoHelperUrl */
    private NostoHelperUrl $nostoHelperUrl;

    /** @var CookieManagerInterface $cookieManager */
    private CookieManagerInterface $cookieManager;

    /** @var CategoryCollectionFactory $categoryCollectionFactory */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /** @var CategoryIndexer $categoryIndexer */
    private CategoryIndexer $categoryIndexer;

    /** @var CategorySyncService $categorySyncService */
    private CategorySyncService $categorySyncService;

    /** @var CategoryRepositoryInterface $categoryRepository */
    private CategoryRepositoryInterface $categoryRepository;

    public function __construct(
        RequestInterface $request,
        CollectionFactory $collectionFactory,
        SyncService $syncService,
        Scope $scope,
        ProductIndexer $productIndexer,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory,
        NostoHelperAccount $nostoHelperAccount,
        Logger $logger,
        OrderRepositoryInterface $orderRepository,
        OrderBuilder $orderBuilder,
        NostoHelperUrl $nostoHelperUrl,
        CookieManagerInterface $cookieManager,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryIndexer $categoryIndexer,
        CategorySyncService $categorySyncService,
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
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderBuilder = $orderBuilder;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->cookieManager = $cookieManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryIndexer = $categoryIndexer;
        $this->categorySyncService = $categorySyncService;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     * @throws AbstractHttpException
     * @throws Exception
     */
    public function execute()
    {
        $store = $this->scope->getStore();

        if ('product' === $this->request->getParam('entity_type')) {
            $product = $this->collectionFactory->create();
            $product->addAttributeToFilter('entity_id', ['eq' => $this->request->getParam('entity_id')]);
            $this->productIndexer->executeRow($product->getFirstItem()->getData('entity_id'));
            $this->productIndexer->doIndex($store, [$product->getFirstItem()->getData('entity_id')]);
            $this->syncService->sync($product, $store);

            $this->messageManager->addSuccessMessage('Product successfully synced.');
        }

        if ('order' === $this->request->getParam('entity_type')) {
            $account = $this->nostoHelperAccount->findAccount($store);
            /** @var Order $order */
            $order = $this->orderRepository->get($this->request->getParam('entity_id'));
            $nostoOrder = $this->orderBuilder->build($order);
            $customerId = $this->cookieManager->getCookie('2c_cId');
            $orderService = new NostoOrderCreate(
                $nostoOrder,
                $account,
                AbstractGraphQLOperation::IDENTIFIER_BY_CID,
                $customerId,
                $this->nostoHelperUrl->getActiveDomain($store)
            );
            $orderService->execute();

            $this->messageManager->addSuccessMessage('Order successfully synced.');
        }

        if ('category' === $this->request->getParam('entity_type')) {
//            $category = $this->categoryCollectionFactory->create();
//            $category->addAttributeToFilter('entity_id', ['eq' => $this->request->getParam('entity_id')]);
            /** @var Category $category */
            $category = $this->categoryRepository->get($this->request->getParam('entity_id'));
            $products = $this->collectionFactory->create();
            $products->addCategoryFilter($category);
            $this->productIndexer->execute($products->getAllIds());
            $this->productIndexer->doIndex($store, [$products->getAllIds()]);
            $this->syncService->sync($products, $store);
//            $this->categoryIndexer->executeRow($category->getFirstItem()->getData('entity_id'));
//            $this->categoryIndexer->doIndex($store, [$category->getFirstItem()->getData('entity_id')]);
//            $this->categorySyncService->sync($category, $store);

            $this->messageManager->addSuccessMessage('Category successfully synced.');
        }

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?entity_type='.$this->request->getParam('entity_type').'&entity_id='.$this->request->getParam('entity_id'));
    }
}
