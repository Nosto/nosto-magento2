<?php

namespace Nosto\Tagging\Model\Product\Observers;

use Magento\Catalog\Model\Product;
use Nosto\Tagging\Model\Product\Observers\Base as ProductObserver;

/**
 * Upsert event observer model.
 * Used to interact with Magento events.
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Update extends ProductObserver
{
    /**
     * @inheritdoc
     */
    protected function doRequest(\NostoServiceProduct $operation)
    {
        $operation->upsert();
    }

    /**
     * @inheritdoc
     */
    protected function validateProduct(Product $product)
    {
        return $product->isVisibleInSiteVisibility();
    }
}