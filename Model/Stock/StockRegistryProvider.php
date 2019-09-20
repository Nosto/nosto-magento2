<?php
namespace Nosto\Tagging\Model\Stock;

use Magento\CatalogInventory\Model\StockRegistryProvider as MagentoStockRegistryProvider;

class StockRegistryProvider extends MagentoStockRegistryProvider
{
    /**
     * @param int[] $productIds
     * @param int $scopeId
     * @return \Magento\CatalogInventory\Api\Data\StockStatusCollectionInterface
     */
    public function getStockStatuses(array $productIds, $scopeId = 0)
    {
        $criteria = $this->stockStatusCriteriaFactory->create();
        $criteria->setProductsFilter($productIds);
        $criteria->setScopeFilter($scopeId);

        return $this->stockStatusRepository->getList($criteria);
    }
}
