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

namespace Nosto\Tagging\Model\Service\Product;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Exception\FilteredProductException;
use Nosto\Exception\NonBuildableProductException;
use Nosto\Model\Product\Product as NostoProduct;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\Repository as NostoProductRepository;

class DefaultProductService implements ProductServiceInterface
{

    /** @var NostoProductBuilder */
    private $nostoProductBuilder;

    /** @var NostoLogger */
    private $logger;

    /** @var NostoProductRepository */
    private $nostoProductRepository;

    /**
     * DefaultProductService constructor.
     * @param NostoProductBuilder $nostoProductBuilder
     * @param NostoProductRepository $nostoProductRepository
     * @param NostoLogger $logger
     */
    public function __construct(
        NostoProductBuilder $nostoProductBuilder,
        NostoProductRepository $nostoProductRepository,
        NostoLogger $logger
    ) {
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->nostoProductRepository = $nostoProductRepository;
        $this->logger = $logger;
    }

    /**
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return NostoProduct|null
     * @suppress PhanTypeMismatchArgument
     * @throws Exception
     */
    public function getProduct(ProductInterface $product, StoreInterface $store)
    {
        /** @var Product $product */
        /** @var Store $store */
        try {
            return $this->nostoProductBuilder->build(
                $this->nostoProductRepository->reloadProduct(
                    $product->getId(),
                    $store->getId()
                ),
                $store
            );
        } catch (NonBuildableProductException $e) {
            $this->logger->exception($e);
            return null;
        } catch (FilteredProductException $e) {
            $this->logger->debug(
                sprintf(
                    'Product filtered out with message: %s',
                    $e->getMessage()
                )
            );
            return null;
        }
    }
}
