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

namespace Nosto\Tagging\Model\Service;

use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Api\Data\ProductIndexInterface;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Index\IndexRepository as NostoIndexRepository;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;

class Product
{
    const NOSTO_SCOPE_TAGGING = 'tagging';
    const NOSTO_SCOPE_API = 'api';

    /**
     * @var NostoIndexService
     */
    private $nostoIndexService;

    /**
     * @var NostoIndexRepository
     */
    private $nostoIndexRepository;

    /**
     * @var Logger
     */
    private $nostoLogger;

    /**
     * Index constructor.
     * @param NostoIndexService $nostoIndexService
     * @param NostoIndexRepository $nostoIndexRepository
     * @param Logger $nostoLogger
     */
    public function __construct(
        NostoIndexService $nostoIndexService,
        NostoIndexRepository $nostoIndexRepository,
        Logger $nostoLogger
    ) {
        $this->nostoIndexService = $nostoIndexService;
        $this->nostoIndexRepository = $nostoIndexRepository;
        $this->nostoLogger = $nostoLogger;
    }

    /**
     * Gets Nosto product from database. If the product doesn't exist or is dirty
     * it will be added / regenerated on-the-fly
     *
     * @param MagentoProduct $product
     * @param Store $store
     * @param string $scope
     * @return NostoProduct|null
     */
    public function getNostoProduct(MagentoProduct $product, Store $store, $scope)
    {
        try {
            $indexedProduct = $this->nostoIndexRepository->getOneByProductAndStore($product, $store);
            if ($indexedProduct === null) {
                $this->nostoIndexService->invalidateOrCreateProductOrParent($product, $store);
                $indexedProduct = $this->nostoIndexRepository->getOneByProductAndStore($product, $store);
            }
            if ($indexedProduct->getIsDirty()) {
                $indexedProduct = $this->nostoIndexService->rebuildDirtyProduct($indexedProduct);
            }
            $nostoProduct = $indexedProduct->getNostoProduct();
            if ($nostoProduct === null) {
                return null;
            }
            if ($scope !== self::NOSTO_SCOPE_API) {
                return $nostoProduct->sanitize();
            }
            return $nostoProduct;
        } catch (\Exception $e) {
            $this->nostoLogger->exception($e);
            return null;
        }
    }
}
