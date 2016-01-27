<?php

namespace Nosto\Tagging\Model\Product\Observers;

use Magento\Catalog\Model\Product;
use Nosto\Tagging\Model\Product\Observers\Base as ProductObserver;

/**
 * Delete event observer model.
 * Used to interact with Magento events.
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Delete extends ProductObserver
{
    /**
     * @inheritdoc
     */
    protected function doRequest(\NostoServiceProduct $operation)
    {
        $operation->delete();
    }

    /**
     * @inheritdoc
     */
    protected function validateProduct(Product $product)
    {
        return true;
    }
}