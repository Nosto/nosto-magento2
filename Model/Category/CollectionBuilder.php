<?php
/**
 * Copyright (c) 2023, Nosto Solutions Ltd
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

namespace Nosto\Tagging\Model\Category;

use Exception;
use Magento\Catalog\Model\Category;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Model\Category\CategoryCollection as NostoCategoryCollection;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Catalog\Model\ResourceModel\Category\Collection as MagentoCategoryCollection;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\ResourceModel\Magento\Category\CollectionBuilder as CategoryCollectionBuilder;
use Nosto\Tagging\Model\Service\Product\Category\CategoryServiceInterface;
use Traversable;

/**
 * A builder class for building collection containing Nosto categories
 */
class CollectionBuilder
{
    /** @var NostoCategoryBuilder */
    private NostoCategoryBuilder $categoryBuilder;

    /** @var CategoryCollectionBuilder */
    private CategoryCollectionBuilder $categoryCollectionBuilder;

    /** @var CategoryServiceInterface */
    private CategoryServiceInterface $categoryService;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /**
     * Collection constructor.
     * @param NostoCategoryBuilder $categoryBuilder
     * @param CategoryCollectionBuilder $categoryCollectionBuilder
     * @param CategoryServiceInterface $categoryService
     * @param NostoLogger $logger
     */
    public function __construct(
        NostoCategoryBuilder $categoryBuilder,
        CategoryCollectionBuilder $categoryCollectionBuilder,
        CategoryServiceInterface $categoryService,
        NostoLogger $logger
    ) {
        $this->categoryBuilder = $categoryBuilder;
        $this->categoryCollectionBuilder = $categoryCollectionBuilder;
        $this->categoryService = $categoryService;
        $this->logger = $logger;
    }

    /**
     * @param Store $store
     * @param $id
     * @return NostoCategoryCollection
     * @throws NostoException
     */
    public function buildSingle(Store $store, $id)
    {
        return $this->load(
            $store,
            $this->categoryCollectionBuilder->buildSingle($store, $id)
        );
    }

    /**
     * @param Store $store
     * @param int $limit
     * @param int $offset
     * @return NostoCategoryCollection
     * @throws NostoException
     */
    public function buildMany(Store $store, int $limit = 100, int $offset = 0)
    {
        return $this->load(
            $store,
            $this->categoryCollectionBuilder->buildMany($store, $limit, $offset)
        );
    }

    /**
     * @param Store $store
     * @param $collection
     * @return NostoCategoryCollection
     * @throws NostoException
     */
    private function load(Store $store, $collection)
    {
        /** @var MagentoCategoryCollection $collection */
        $categories = new NostoCategoryCollection();
        $items = $collection->load();
        if ($items instanceof Traversable === false && !is_array($items)) {
            throw new NostoException(
                sprintf('Invalid collection type %s for category export', get_class($collection))
            );
        }
        foreach ($items as $category) {
            /** @var Category $category */
            try {
                $nostoCategory = $this->categoryBuilder->build(
                    $category,
                    $store
                );
                if ($nostoCategory !== null) {
                    $categories->append($nostoCategory);
                }
            } catch (Exception $e) {
                $this->logger->exception($e);
            }
        }
        return $categories;
    }
}
