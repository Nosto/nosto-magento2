<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;

/**
 * Variation block used for outputting the variation ID on the stores pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Variation extends Template
{
    /**
     * @inheritdoc
     */
    protected $_template = 'variation.phtml';

    /**
     * Returns the currently used variation ID, i.e. the stores currency code.
     *
     * @return string the variation ID.
     */
    public function getVariationId()
    {
        $store = $this->_storeManager->getStore();
        return $store->getCurrentCurrencyCode();
    }
}
