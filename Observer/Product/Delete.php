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

namespace Nosto\Tagging\Observer\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableProduct;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Operation\UpsertProduct;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use Nosto\Types\Product\ProductInterface;
use Psr\Log\LoggerInterface;

/**
 * Delete event observer model.
 * Used to interact with Magento events.
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Delete extends Base
{
    private $nostoProductBuilder;

    /**
     * Delete constructor.
     *
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoProductBuilder $nostoProductBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param ModuleManager $moduleManager
     * @param ProductFactory $productFactory
     * @param ConfigurableProduct $configurableProduct
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        NostoProductBuilder $nostoProductBuilder,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ModuleManager $moduleManager,
        ProductFactory $productFactory,
        ConfigurableProduct $configurableProduct
    ) {
        parent::__construct(
            $nostoHelperData,
            $nostoHelperAccount,
            $nostoProductBuilder,
            $storeManager,
            $logger,
            $moduleManager,
            $productFactory,
            $configurableProduct
        );

        $this->nostoProductBuilder = $nostoProductBuilder;
    }

    /**
     * @inheritdoc
     */
    public function doRequest(UpsertProduct $operation)
    {
        $operation->upsert();
    }

    /**
     * @inheritdoc
     */
    public function validateProduct(Product $product)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function buildProduct(Product $product, StoreInterface $store)
    {
        $product = $this->nostoProductBuilder->build($product, $store);
        return $product->setAvailability(ProductInterface::DISCONTINUED);
    }
}
