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

namespace Nosto\Tagging\Model\Product\Index;

use Magento\Framework\Model\AbstractModel;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Model\ResourceModel\Product\Index as NostoIndex;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;
use Magento\Catalog\Api\Data\ProductInterface as MagentoProductInterface;

class Index extends AbstractModel implements ProductIndexInterface
{
    const VALUE_IS_DIRTY = "1";
    const VALUE_IS_NOT_DIRTY = "0";
    const VALUE_NOT_IN_SYNC = "0";

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * @inheritdoc
     */
    public function getProductId()
    {
        return $this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritdoc
     */
    public function getInSync()
    {
        return $this->getData(self::IN_SYNC);
    }

    /**
     * @inheritdoc
     */
    public function getIsDirty()
    {
        return $this->getData(self::IS_DIRTY);
    }

    /**
     * @inheritdoc
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * @inheritdoc
     */
    public function getProductData()
    {
        return $this->getData(self::PRODUCT_DATA);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        return $this->setData(self::ID, $id);
    }

    /**
     * @inheritdoc
     */
    public function setProductId($productId)
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritdoc
     */
    public function setInSync($inSync)
    {
        return $this->setData(self::IN_SYNC, $inSync);
    }

    /**
     * @inheritdoc
     */
    public function setIsDirty($isDirty)
    {
        return $this->setData(self::IS_DIRTY, $isDirty ? self::VALUE_IS_DIRTY : self::VALUE_IS_NOT_DIRTY);
    }

    /**
     * @inheritdoc
     */
    public function setStoreId($storeId)
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritdoc
     */
    public function setProductData($productData)
    {
        return $this->setData(self::PRODUCT_DATA, $productData);
    }

    /**
     * @inheritDoc
     */
    public function setNostoProduct(NostoProductInterface $product)
    {
        return $this->setProductData(serialize($product));
    }

    /**
     * @inheritDoc
     */
    public function getNostoProduct(): NostoProductInterface
    {
        return unserialize($this->getProductData());
    }

    /**
     * @inheritDoc
     */
    public function setStore(StoreInterface $store)
    {
        return $this->setStoreId($store->getId());
    }

    /**
     * @inheritDoc
     */
    public function setMagentoProduct(MagentoProductInterface $product)
    {
        return $this->setProductId($product->getId());
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    public function _construct()
    {
        $this->_init(NostoIndex::class);
    }
}
