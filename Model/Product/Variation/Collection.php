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

namespace Nosto\Tagging\Model\Product\Variation;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\GroupManagement;
use Magento\Store\Model\Store;
use Nosto\Nosto;
use Nosto\Object\Product\VariationCollection;
use Nosto\Object\Product\Product as NostoProduct;
use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Nosto\Tagging\Model\Product\Variation\Builder as VariationBuilder;

class Collection
{
    private $nostoVariationBuilder;
    private $customerGroupManager;
    private $groupRepository;
    private $variationBuilder;

    /**
     * Collection constructor.
     * @param Builder $nostoVariationBuilder
     * @param GroupManagement $customerGroupManager
     * @param GroupRepository $groupRepository
     */
    public function __construct(
        Builder $nostoVariationBuilder,
        GroupManagement $customerGroupManager,
        GroupRepository $groupRepository,
        VariationBuilder $variationBuilder
    ) {
        $this->nostoVariationBuilder = $nostoVariationBuilder;
        $this->customerGroupManager = $customerGroupManager;
        $this->groupRepository = $groupRepository;
        $this->variationBuilder = $variationBuilder;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return VariationCollection
     * @suppress PhanTypeMismatchArgument
     */
    public function build(Product $product, NostoProduct $nostoProduct, Store $store)
    {
        $collection = new VariationCollection();
        $groups = $this->customerGroupManager->getLoggedInGroups();
        foreach ($groups as $group) {
            // For some (broken?) Magento setups the default group / default
            // variation is also part of the customer groups
            if ($group->getCode() === (string)$nostoProduct->getVariationId()) {
                continue;
            }
            /** @var \Magento\Customer\Model\Data\Group $group */
            $collection->append(
                $this->variationBuilder->build(
                    $product,
                    $nostoProduct,
                    $store,
                    $group
                )
            );
        }
        return $collection;
    }
}
