<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Api\Data;

use Magento\Store\Api\Data\StoreInterface;
use Nosto\Object\Product\Product;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;
use Magento\Catalog\Api\Data\ProductInterface as MagentoProductInterface;

interface ProductIndexInterface
{
    const ID = 'id';
    const PRODUCT_ID = 'product_id';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const IN_SYNC = 'in_sync';
    const IS_DIRTY = 'is_dirty';
    const STORE_ID = 'store_id';
    const PRODUCT_DATA = 'product_data';

    /**
     * Get row id
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get product id
     *
     * @return int|null
     */
    public function getProductId();

    /**
     * Get created at time
     *
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * Get updated at time
     *
     * @return \DateTime
     */
    public function getUpdatedAt();

    /**
     * Get in sync
     *
     * @return boolean
     */
    public function getInSync();

    /**
     * Get is dirty
     *
     * @return boolean
     */
    public function getIsDirty();

    /**
     * Get store id
     *
     * @return int
     */
    public function getStoreId();

    /**
     * Get product data
     *
     * @return string
     */
    public function getProductData();

    /**
     * Set id
     *
     * @param int $id
     * @return self
     */
    public function setId($id);

    /**
     * Set product id
     *
     * @param int $productId
     * @return self
     */
    public function setProductId($productId);

    /**
     * Set in sync
     *
     * @param boolean $inSync
     * @return self
     */
    public function setInSync($inSync);

    /**
     * Set is dirty to false or true
     *
     * @param boolean $isDirty
     * @return self
     */
    public function setIsDirty($isDirty);

    /**
     * Set store id
     *
     * @param int $storeId
     * @return self
     */
    public function setStoreId($storeId);

    /**
     * Set product data
     *
     * @param string $productData
     * @return self
     */
    public function setProductData($productData);

    /**
     * Set created at time
     *
     * @param \DateTime $createdAt
     * @return self
     */
    public function setCreatedAt(\DateTime $createdAt);

    /**
     * Set updated at time
     *
     * @param \DateTime $updatedAt
     * @return self
     */
    public function setUpdatedAt(\DateTime $updatedAt);

    /**
     * @param NostoProductInterface $product
     * @return self
     */
    public function setNostoProduct(NostoProductInterface $product);

    /**
     * @param MagentoProductInterface $product
     * @return self
     */
    public function setMagentoProduct(MagentoProductInterface $product);

    /**
     * @return NostoProductInterface|null
     */
    public function getNostoProduct();

    /**
     * @param StoreInterface $store
     * @return self
     */
    public function setStore(StoreInterface $store);
}
