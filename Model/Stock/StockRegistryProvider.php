<?php
namespace Nosto\Tagging\Model\Stock;

use Magento\CatalogInventory\Api\Data\StockStatusCollectionInterface as StockStatusCollectionInterface;
use Magento\CatalogInventory\Model\StockRegistryProvider as MagentoStockRegistryProvider;

class StockRegistryProvider extends MagentoStockRegistryProvider
{
    const DEFAULT_STOCK_SCOPE = 0;

    /**
     * @param int[] $productIds
     * @param int $scopeId
     * @return StockStatusCollectionInterface
     */
    public function getStockStatuses(array $productIds, $scopeId = self::DEFAULT_STOCK_SCOPE)
    {
        $criteria = $this->stockStatusCriteriaFactory->create();
        $criteria->setProductsFilter($productIds);
        $criteria->setScopeFilter($scopeId);

        return $this->stockStatusRepository->getList($criteria);
    }
}
