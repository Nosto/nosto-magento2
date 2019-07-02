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

namespace Nosto\Tagging\Model\ResourceModel;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Customer\Model\GroupManagement;
use Magento\Store\Model\Store;

class Sku extends ProductResource
{
    const CATALOG_PRODUCT_PRICE_INDEX_TABLE = "catalog_product_index_price";
    const CATALOG_INVENTORY_STOCK_STATUS_TABLE = "cataloginventory_stock_status";

    /**
     * Fetches prices for the SKUs
     *
     * @param Store $store
     * @param array $skuIds
     * @return array
     */
    public function getSkusByIds(
        Store $store,
        array $skuIds
    ): array {
        $gid = (string)GroupManagement::NOT_LOGGED_IN_ID;
        $select = $this->_resource->getConnection()->select()
            ->from(["cpip" => $this->_resource->getTableName(self::CATALOG_PRODUCT_PRICE_INDEX_TABLE)])
            ->joinInner(
                ["ciss" => $this->_resource->getTableName(self::CATALOG_INVENTORY_STOCK_STATUS_TABLE)],
                "cpip.entity_id=ciss.product_id"
            )
            ->where("ciss.stock_status = ?", 1) //@codingStandardsIgnoreLine
            ->where("cpip.website_id = ?", $store->getWebsiteId()) //@codingStandardsIgnoreLine
            ->where("cpip.entity_id IN(?)", $skuIds) //@codingStandardsIgnoreLine
            ->where("cpip.customer_group_id = ?", $gid); //@codingStandardsIgnoreLine

        return $this->_resource->getConnection()->fetchAll($select); //@codingStandardsIgnoreLine
    }
}
