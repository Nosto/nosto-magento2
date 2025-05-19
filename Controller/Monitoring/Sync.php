<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Exception;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Indexer\Model\IndexerFactory;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\NostoException;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Tagging\Model\Indexer\ProductIndexer;
use Nosto\Tagging\Model\ResourceModel\Magento\Product\CollectionFactory as CollectionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Nosto\Tagging\Helper\Scope;
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

    public function __construct(
        RequestInterface $request,
        CollectionFactory $collectionFactory,
        SyncService $syncService,
        Scope $scope,
        ProductIndexer $productIndexer,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory
    ) {
        $this->request = $request;
        $this->collectionFactory = $collectionFactory;
        $this->syncService = $syncService;
        $this->scope = $scope;
        $this->productIndexer = $productIndexer;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * @throws NostoException
     * @throws MemoryOutOfBoundsException
     * @throws AbstractHttpException
     * @throws Exception
     */
    public function execute()
    {
        if ('product' === $this->request->getParam('entity_type')) {
            $product = $this->collectionFactory->create();
            $product->addAttributeToFilter('entity_id', ['eq' => $this->request->getParam('entity_id')]);
            $store = $this->scope->getStore();
            $this->productIndexer->executeRow($product->getFirstItem()->getData('entity_id'));
            $this->productIndexer->doIndex($store, [$product->getFirstItem()->getData('entity_id')]);
            $this->syncService->sync($product, $store);

            $this->messageManager->addSuccessMessage('Product successfully synced.');
        }

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/indexer?entity_type='.$this->request->getParam('entity_type').'&entity_id='.$this->request->getParam('entity_id'));
    }
}
