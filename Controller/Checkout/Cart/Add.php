<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Controller\Checkout\Cart;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Controller\Cart\Add as MageAdd;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as MageAttribute;
use Magento\Store\Model\StoreManager;

/**
 * Class Add
 * @package Nosto\Tagging\Controller\Checkout\Cart
 */
class Add
{
    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var StoreManager */
    private $storeManager;

    /**
     * Add constructor.
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManager $storeManager
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        StoreManager $storeManager
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Method executed before magento's native add to cart controller
     * Checks if request has product attributes and set them before
     * returning to magento's controller
     *
     * @param MageAdd $add
     * @param callable $proceed
     * @return mixed
     */
    public function aroundExecute(MageAdd $add, callable $proceed)
    {
        $params = $add->getRequest()->getParams();
        if (isset($params['super_attribute'])) {
            return $proceed();
        }
        $productId = $params['product'];
        $skuId = $add->getRequest()->getParam('sku');
        $product = $this->initProduct($productId);
        $parentType = false;
        if ($product) {
            $parentType = $product->getTypeInstance();
        }

        if ($parentType && $parentType instanceof ConfigurableType) {
            $skuProduct = $this->initProduct($skuId);
            $configurableAttributes = $parentType->getConfigurableAttributesAsArray($product);
            foreach ($configurableAttributes as $configurableAttribute) {
                $attributeCode = $configurableAttribute['attribute_code'];
                $attribute = $skuProduct->getResource()->getAttribute($attributeCode);
                if ($attribute instanceof MageAttribute) {
                    $attributeId = $attribute->getId();
                    $attributeValueId = $skuProduct->getData($attributeCode);
                    if ($attributeId && $attributeValueId) {
                        $attributeOptions[$attributeId] = $attributeValueId;
                    }
                }
            }
        }
        if (!empty($attributeOptions)) {
            $params['super_attribute'] = $attributeOptions;
            $add->getRequest()->setParams($params);
        }
        return $proceed();
    }

    /**
     * Initialize product instance from request data
     *
     * @param $productId
     * @return ProductInterface|bool
     */
    protected function initProduct($productId)
    {
        try {
            $storeId = null;
            $store = $this->storeManager->getStore();
            if ($store) {
                $storeId = $store->getId();
            }
            return $this->productRepository->getById($productId, false, $storeId);
        } catch (\Exception $e) {
            return false;
        }
    }

}
