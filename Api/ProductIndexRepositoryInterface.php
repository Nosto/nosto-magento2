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

namespace Nosto\Tagging\Api;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Api\Data\StoreInterface;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Api\Data\ProductIndexSearchResultsInterface;

interface ProductIndexRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Save Queue entry
     *
     * @param ProductIndexInterface $productIndex
     * @return ProductIndexInterface
     */
    public function save(ProductIndexInterface $productIndex);

    /**
     * Delete productIndex
     *
     * @param ProductIndexInterface $productIndex
     */
    public function delete(ProductIndexInterface $productIndex);

    /**
     * Returns all entries by product id
     *
     * @param int $productId
     * @return ProductIndexSearchResultsInterface
     */
    public function getByProductId($productId);

    /**
     * Returns row from id
     *
     * @param int $id
     * @return ProductIndexInterface
     */
    public function getById($id);

    /**
     * Returns entry by product and store
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return ProductIndexInterface|null
     */
    public function getOneByProductAndStore(ProductInterface $product, StoreInterface $store);

    /**
     * @param int $productId
     * @param int $storeId
     * @return ProductIndexInterface|null
     */
    public function getByProductIdAndStoreId(int $productId, int $storeId);
}
