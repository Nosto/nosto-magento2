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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Helper\SerializationHelper;
use Nosto\Tagging\CustomerData\CartTagging;
use Nosto\Tagging\CustomerData\CustomerTagging;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class TaggingProvider extends Template
{
    /**
     * Default type assigned to the page if none is set in the layout xml.
     */
    private const DEFAULT_PAGE_TYPE = 'other';

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
    /** @var Product $product */
    private Product $product;
    /** @var Search $search */
    private Search $search;
    /** @var Variation $variation */
    private Variation $variation;
    /** @var Knockout $knockout */
    private Knockout $knockout;
    /** @var NostoLogger $logger */
    private NostoLogger $logger;
    /** @var Json $json */
    private Json $json;

    public function __construct(
        Context $context,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperData $nostoHelperData,
        NostoHelperCustomer $nostoHelperCustomer,
        NostoHelperVariation $nostoHelperVariation,
        Category $category,
        Product $product,
        Search $search,
        Variation $variation,
        Knockout $knockout,
        NostoLogger $logger,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperCustomer = $nostoHelperCustomer;
        $this->nostoHelperVariation = $nostoHelperVariation;
        $this->category = $category;
        $this->product = $product;
        $this->search = $search;
        $this->variation = $variation;
        $this->knockout = $knockout;
        $this->logger = $logger;
        $this->json = $json;
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

        $result['cart'] = $this->getCurrentCart();
        $result['customer'] = $this->getCurrentCustomer();
        $result['variation'] = $this->getCurrentVariation();

        if ($pageType === 'product') {
            $productData = $this->getCurrentProducts();
            if ($productData) {
                $result['products'] = [$productData];
            }
        }

        if (in_array($pageType, ['category', 'product', 'search'])) {
            $categoryString = $this->getCurrentCategories();
            if ($categoryString) {
                $result['categories'] = [$categoryString];
            }
        }

        if ($pageType === 'search') {
            $result['searchTerm'] = $this->getCurrentSearchTerm();
        }

        return $result;
    }
    
    /**
     * Explicitly expose setData method for GraphQL and API usage
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setData($key, $value = null)
    {
        return parent::setData($key, $value);
    }

    /**
     * Get tagging configuration as JSON ready for JavaScript context
     *
     * @return string
     */
    public function getTaggingConfigForJs()
    {
        return $this->json->serialize($this->getTaggingConfig());
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
            $this->logger->debug(
                'Error getting product data for tagging: ' . $e->getMessage(),
                ['exception' => $e]
            );
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
            if ($this->isHyva()) {
                // For Hyva, we need to get cart data from the customer data section
                $objectManager = ObjectManager::getInstance();
                $cartTagging = $objectManager->create(CartTagging::class);
                $cartData = $cartTagging->getSectionData();
                
                // Remove null values
                if (isset($cartData['items']) && is_array($cartData['items'])) {
                    foreach ($cartData['items'] as &$item) {
                        $item = array_filter($item, static function ($value) {
                            return $value !== null;
                        });
                    }
                }
                return array_filter($cartData, function ($value) {
                    return $value !== null && (!is_string($value) || trim($value) !== '');
                });
            }
            // For Luma, we get it from the JS layout
            $cartData = json_decode($this->knockout->getJsLayout(), true);
            if (array_key_exists('components', $cartData) &&
                array_key_exists('cartTagging', $cartData['components']) &&
                array_key_exists('component', $cartData['components']['cartTagging'])) {
                return $cartData;
            }
            return null;
        } catch (\Exception $e) {
            // Cart data not available
            $this->logger->debug(
                'Error getting cart data for tagging: ' . $e->getMessage(),
                ['exception' => $e]
            );
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
            if ($this->isHyva()) {
                // For Hyva, we need to get customer data from the customer data section
                $objectManager = ObjectManager::getInstance();
                $customerTagging = $objectManager->create(CustomerTagging::class);
                $customerData = $customerTagging->getSectionData();
                
                // Remove null values so it won't break the tagging provider implementation
                return array_filter($customerData, function ($value) {
                    return $value !== null && (!is_string($value) || trim($value) !== '');
                });
            }
            // For Luma, we get it from the JS layout
            $customerData = json_decode($this->knockout->getJsLayout(), true);
            if (array_key_exists('components', $customerData) &&
                array_key_exists('customerTagging', $customerData['components']) &&
                array_key_exists('component', $customerData['components']['customerTagging'])) {
                return $customerData;
            }
            return null;
        } catch (\Exception $e) {
            // Customer data not available
            $this->logger->debug(
                'Error getting customer data for tagging: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        return null;
    }

    /**
     * Get category data for the current page
     *
     * @return string|null
     */
    private function getCurrentCategories()
    {
        try {
            $category = $this->category->getAbstractObject();
            if ($category) {
                // Return only the category string instead of the whole category object
                return $category->getCategoryString();
            }
        } catch (\Exception $e) {
            // Category not available
            $this->logger->debug(
                'Error getting category data for tagging: ' . $e->getMessage(),
                ['exception' => $e]
            );
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
            return $this->search->getNostoSearchTerm();
        } catch (\Exception $e) {
            // Search term not available
            $this->logger->debug(
                'Error getting search term for tagging: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        return null;
    }

    /**
     * Get variation data (currency or price variation)
     *
     * @return string|null
     */
    private function getCurrentVariation()
    {
        $store = $this->nostoHelperScope->getStore(true);

        // Check if multiple currencies are used or price variations are enabled
        if ($this->variation->hasMultipleCurrencies() ||
            ($this->nostoHelperData->isPricingVariationEnabled($store) &&
                !$this->nostoHelperVariation->isDefaultVariationCode($this->nostoHelperCustomer->getGroupCode()))
        ) {
            return $this->variation->getVariationId();
        }

        return null;
    }

    /**
     * Check if Nosto is enabled
     *
     * @return bool
     */
    public function isNostoEnabled()
    {
        $store = $this->nostoHelperScope->getStore(true);
        return $this->nostoHelperAccount->nostoInstalledAndEnabled($store) &&
            $this->nostoHelperData->isTaggingProvidersEnabled($store);
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
            // Unable to determine if reload recs is enabled,
            // likely the store can't be loaded from the request
            $this->logger->debug(
                'Error determining if recommendations should reload after add to cart: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return $reload;
    }
}
