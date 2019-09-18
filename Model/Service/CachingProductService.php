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

use Exception;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Index\IndexRepository as NostoIndexRepository;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;
use Nosto\Tagging\Model\Service\Serializer\SerializedProductBuilder;
use Nosto\Types\Product\ProductInterface as NostoProductInterface;

class CachingProductService implements ProductServiceInterface
{

    /** @var NostoIndexRepository */
    private $nostoIndexRepository;

    /** @var Logger */
    private $nostoLogger;

    /** @var ProductServiceInterface */
    private $nostoProductService;

    /** @var NostoIndexService */
    private $nostoIndexService;

    /** @var SerializedProductBuilder */
    private $serializedProductBuilder;

    /**
     * Index constructor.
     * @param NostoIndexRepository $nostoIndexRepository
     * @param Logger $nostoLogger
     * @param ProductServiceInterface $nostoProductService
     * @param NostoIndexService $nostoIndexService
     * @param SerializedProductBuilder $serializedProductBuilder
     */
    public function __construct(
        NostoIndexRepository $nostoIndexRepository,
        Logger $nostoLogger,
        ProductServiceInterface $nostoProductService,
        NostoIndexService $nostoIndexService,
        SerializedProductBuilder $serializedProductBuilder
    ) {
        $this->nostoIndexRepository = $nostoIndexRepository;
        $this->nostoLogger = $nostoLogger;
        $this->nostoProductService = $nostoProductService;
        $this->nostoIndexService = $nostoIndexService;
        $this->serializedProductBuilder = $serializedProductBuilder;
    }

    /**
     * Get Nosto Product
     * If is not indexed or dirty, rebuilds, saves product to the indexed table
     * and returns NostoProduct from indexed product
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return NostoProductInterface|null
     */
    public function getProduct(ProductInterface $product, StoreInterface $store)
    {
        try {
            $indexedProduct = $this->nostoIndexRepository->getOneByProductAndStore($product, $store);
            if ($indexedProduct === null || $indexedProduct->getIsDirty()) {
                $fullProduct = $this->nostoProductService->getProduct($product, $store);
                if ($fullProduct === null) {
                    return null;
                }
                $this->nostoIndexService->updateOrCreateDirtyEntity($fullProduct, $store);
                $indexedProduct = $this->nostoIndexRepository->getOneByProductAndStore($product, $store);
            }
            return $this->serializedProductBuilder->fromString(
                $indexedProduct->getProductData()
            );
        } catch (Exception $e) {
            $this->nostoLogger->exception($e);
            return null;
        }
    }
}
