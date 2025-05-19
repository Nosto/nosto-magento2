<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ObjectManager;
use Magento\Indexer\Model\IndexerFactory;
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

    public function __construct(
        RequestInterface $request,
        CollectionFactory $collectionFactory,
        SyncService $syncService,
        Scope $scope,
        private readonly ProductIndexer $productIndexer,
        private readonly IndexerFactory $indexerFactory
    ) {
        $this->request = $request;
        $this->collectionFactory = $collectionFactory;
        $this->syncService = $syncService;
        $this->scope = $scope;
    }

    public function execute()
    {
        $x = $this->collectionFactory->create();
        $x->addAttributeToFilter('entity_id', ['eq' => $this->request->getParam('product_id')]);
        $store = $this->scope->getStore();
        $this->productIndexer->executeRow($this->request->getParam('product_id'));
        $this->productIndexer->doIndex($store, [$this->request->getParam('product_id')]);
        $this->syncService->sync($x, $store);
//        $output = shell_exec('bin/magento indexer:reset nosto_index_product');
//        $output1 = shell_exec('bin/magento indexer:reindex nosto_index_product');
//        $output2 = shell_exec('bin/magento queue:consumers:start nosto_product_sync.update');
//        $indexer = $this->indexerFactory->create();
//        $indexer->load('nosto_index_product');
//        $indexer->reindexAll();

        dump('test 22');
    }
}
