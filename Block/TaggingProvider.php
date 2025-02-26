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
namespace Nosto\Tagging\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Helper\SerializationHelper;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;

class TaggingProvider extends Template
{
    /**
     * Default type assigned to the page if none is set in the layout xml.
     */
    private const DEFAULT_PAGE_TYPE = 'unknown';

    /** @var Registry */
    private Registry $registry;
    /** @var NostoCategoryBuilder */
    private NostoCategoryBuilder $categoryBuilder;
    /** @var NostoHelperScope */
    private NostoHelperScope $nostoHelperScope;
    /** @var NostoHelperData */
    private NostoHelperData $nostoHelperData;
    /** @var NostoHelperCustomer */
    private NostoHelperCustomer $nostoHelperCustomer;
    /** @var NostoHelperVariation */
    private NostoHelperVariation $nostoHelperVariation;
    /** @var NostoHelperAccount */
    private NostoHelperAccount $nostoHelperAccount;

    /** @var Category $category */
    private Category $category;
    /** @var Element $element */
    private Element $element;
    /** @var Embed $embed */
    private Embed $embed;
    /** @var Meta $meta */
    private Meta $meta;
    /** @var Order $order */
    private Order $order;
    /** @var PageType $pageType */
    private PageType $pageType;
    /** @var Product $product */
    private Product $product;
    /** @var Search $search */
    private Search $search;
    /** @var Variation $variation */
    private Variation $variation;
    /** @var Knockout $knockout */
    private Knockout $knockout;

    public function __construct(
        Context $context,
        Registry $registry,
        NostoCategoryBuilder $categoryBuilder,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperData $nostoHelperData,
        NostoHelperCustomer $nostoHelperCustomer,
        NostoHelperVariation $nostoHelperVariation,
        Category $category,
        Element $element,
        Embed $embed,
        Meta $meta,
        Order $order,
        PageType $pageType,
        Product $product,
        Search $search,
        Variation $variation,
        Knockout $knockout,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperCustomer = $nostoHelperCustomer;
        $this->nostoHelperVariation = $nostoHelperVariation;
        $this->category = $category;
        $this->element = $element;
        $this->embed = $embed;
        $this->meta = $meta;
        $this->order = $order;
        $this->pageType = $pageType;
        $this->product = $product;
        $this->search = $search;
        $this->variation = $variation;
        $this->knockout = $knockout;
    }

    /**
     * Gets all tagging configuration for the current page
     *
     * @return array
     */
    public function getTaggingConfig()
    {
        $pageType = $this->getCurrentPageType();
        $result = [
            'pageType' => $pageType,
        ];

        // Add cart data if available
        try {
            $result['cart'] = $this->getCurrentCart();
        } catch (\Exception $e) {
            // Cart data not available
        }

        // Add customer data if available
        try {
            $result['customer'] = $this->getCurrentCustomer();
        } catch (\Exception $e) {
            // Customer data not available
        }

        // Add variation data if multiple currencies or price variations are enabled
        try {
            $result['variation'] = $this->getCurrentVariation();
        } catch (\Exception $e) {
            // Variation data not available
        }

        if ($pageType === 'product') {
            try {
                $productData = $this->getCurrentProducts();
                if ($productData) {
                    $result['products'] = [$productData];
                }
            } catch (\Exception $e) {
                // Product data not available
            }
        }

        if (in_array($pageType, ['category', 'product', 'search'])) {
            try {
                $categoryData = $this->getCurrentCategories();
                if ($categoryData) {
                    $result['categories'] = [$categoryData];
                }
            } catch (\Exception $e) {
                // Category data not available
            }
        }

        if ($pageType === 'search') {
            try {
                $result['searchTerm'] = $this->getCurrentSearchTerm();
            } catch (\Exception $e) {
                // Search term not available
            }
        }

        return $result;
    }

    /**
     * Determine if we're in the Hyva theme area
     *
     * @return bool
     */
    public function isHyva()
    {
        return $this->knockout->isHyva();
    }

    /**
     * Get the page type for the current page
     *
     * @return string
     */
    private function getCurrentPageType()
    {
        return $this->getData('page_type') ?: self::DEFAULT_PAGE_TYPE;
    }

    /**
     * Get product data for the current product page
     *
     * @return array|null
     */
    private function getCurrentProducts()
    {
        try {
            $product = $this->product->getAbstractObject();
            if ($product) {
                return SerializationHelper::toArray($product);
            }
        } catch (\Exception $e) {
            // Product not available
        }
        return null;
    }

    /**
     * Get cart data for the current session
     *
     * @return array|null
     */
    private function getCurrentCart()
    {
        try {
            $cartData = json_decode($this->knockout->getJsLayout(), true);
            return $cartData['components']['cartTagging']['component'] ? $cartData : null;
        } catch (\Exception $e) {
            // Cart data not available
        }
        return null;
    }

    /**
     * Get customer data for the current session
     *
     * @return array|null
     */
    private function getCurrentCustomer()
    {
        try {
            $customerData = json_decode($this->knockout->getJsLayout(), true);
            return $customerData['components']['customerTagging']['component'] ? $customerData : null;
        } catch (\Exception $e) {
            // Customer data not available
        }
        return null;
    }

    /**
     * Get category data for the current page
     *
     * @return array|null
     */
    private function getCurrentCategories()
    {
        try {
            $category = $this->category->getAbstractObject();
            if ($category) {
                return SerializationHelper::toArray($category);
            }
        } catch (\Exception $e) {
            // Category not available
        }
        return null;
    }

    /**
     * Get search term for the current search page
     *
     * @return string|null
     */
    private function getCurrentSearchTerm()
    {
        try {
            if ($this->search instanceof Search) {
                return $this->search->getNostoSearchTerm();
            }
        } catch (\Exception $e) {
            // Search term not available
        }
        return null;
    }

    /**
     * Get variation data (currency or price variation)
     *
     * @return array|null
     */
    private function getCurrentVariation()
    {
        $store = $this->nostoHelperScope->getStore(true);

        // Check if multiple currencies are used or price variations are enabled
        if ($this->variation->hasMultipleCurrencies() ||
            ($this->nostoHelperData->isPricingVariationEnabled($store) &&
                !$this->nostoHelperVariation->isDefaultVariationCode($this->nostoHelperCustomer->getGroupCode()))
        ) {
            return [
                'variation_id' => $this->variation->getVariationId()
            ];
        }

        return null;
    }

    /**
     * Check if Nosto is enabled
     *
     * @return bool
     */
    /**
     * Check if Nosto is enabled
     *
     * @return bool
     */
    public function isNostoEnabled()
    {
        $store = $this->nostoHelperScope->getStore(true);
        return $this->nostoHelperAccount->nostoInstalledAndEnabled($store);
    }

    /**
     * Get relevant path to template
     *
     * @return string
     * @suppress PhanTypeMismatchReturn
     */
    public function getTemplate()
    {
        $template = null;
        if ($this->isNostoEnabled()) {
            $template = parent::getTemplate();
        }

        return $template;
    }

    /**
     * Retrieve serialized JS layout configuration ready to use in template
     *
     * @return string
     */
    public function getJsLayout()
    {
        $jsLayout = '';
        if ($this->isNostoEnabled()) {
            $jsLayout = parent::getJsLayout();
        }

        return $jsLayout;
    }

    /**
     * Check if reload recommendations after add to cart is enabled
     *
     * @return int
     */
    public function isReloadRecsAfterAtcEnabled()
    {
        $reload = 0;
        try {
            $store = $this->_storeManager->getStore();
            $reload = $this->nostoHelperData->isReloadRecsAfterAtcEnabled($store);
        } catch (\Exception $e) {
            // Unable to determine if reload recs is enabled
        }

        return $reload;
    }
}
