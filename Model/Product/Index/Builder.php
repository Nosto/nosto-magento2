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

use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Tagging\Model\Product\BuilderTrait;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Builder
{
    use BuilderTrait {
        BuilderTrait::__construct as builderTraitConstruct; // @codingStandardsIgnoreLine
    }

    /** @var IndexFactory  */
    private $nostoIndexFactory;

    /** @var NostoProductBuilder */
    private $nostoProductBuilder;

    /**
     * Builder constructor.
     * @param IndexFactory $nostoIndexFactory
     * @param NostoProductBuilder $nostoProductBuilder
     */
    public function __construct(
        IndexFactory $nostoIndexFactory,
        NostoProductBuilder $nostoProductBuilder
    ) {
        $this->nostoIndexFactory = $nostoIndexFactory;
        $this->nostoProductBuilder = $nostoProductBuilder;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return Index
     * @throws NostoException
     */
    public function build(
        Product $product,
        Store $store
    ) {
        $nostoProduct = $this->nostoProductBuilder->build($product, $store);
        if ($nostoProduct instanceof NostoProduct) {
            $timeZone = new TimezoneInterface;
            $productIndex = $this->nostoIndexFactory->create();
            $productIndex->setProductId($nostoProduct->getProductId());
            $productIndex->setCreatedAt($timeZone->date());
            $productIndex->setInSync(false);
            $productIndex->setIsDirty(false);
            $productIndex->setUpdatedAt($timeZone->date());
            $productIndex->setNostoProduct($nostoProduct);
            $productIndex->setStore($store);
            return $productIndex;
        }

        throw new NostoException(
            'Could not build Nosto product for id ' . $product->getId()
        );
    }
}
