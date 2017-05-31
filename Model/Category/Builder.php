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

namespace Nosto\Tagging\Model\Category;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\ManagerInterface;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Sentry as NostoHelperSentry;

class Builder
{
    private $nostoHelperSentry;
    private $categoryRepository;
    private $eventManager;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param NostoHelperSentry $nostoHelperSentry
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        NostoHelperSentry $nostoHelperSentry,
        ManagerInterface $eventManager
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->nostoHelperSentry = $nostoHelperSentry;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function buildCategories(Product $product)
    {
        $categories = [];
        foreach ($product->getCategoryCollection() as $category) {
            $categoryString = $this->build($category);
            if (!empty($categoryString)) {
                $categories[] = $categoryString;
            }
        }

        return $categories;
    }

    /**
     * @param Category $category
     * @return string
     */
    public function build(Category $category)
    {
        $nostoCategory = '';
        try {
            $data = [];
            $path = $category->getPath();
            foreach (explode('/', $path) as $categoryId) {
                $category = $this->categoryRepository->get($categoryId);
                if ($category instanceof Category
                    && $category->getLevel() > 1
                    && !empty($category->getName())
                ) {
                    $data[] = $category->getName();
                }
            }
            $nostoCategory = count($data) ? '/' . implode('/', $data) : '';
        } catch (NostoException $e) {
            $this->nostoHelperSentry->error($e);
        }
        if (empty($nostoCategory)) {
            $nostoCategory = null;
        } else {
            $this->eventManager->dispatch(
                'nosto_category_load_after',
                ['category' => $nostoCategory]
            );
        }

        return $nostoCategory;
    }
}
