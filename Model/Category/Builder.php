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

namespace Nosto\Tagging\Model\Category;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Event\ManagerInterface;
use Magento\Store\Model\Store;
use Nosto\Model\Category\Category as NostoCategory;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\Product\Category\CategoryServiceInterface as NostoCategoryService;

class Builder
{
    /** @var CategoryRepositoryInterface */
    private CategoryRepositoryInterface $categoryRepository;

    /** @var ManagerInterface */
    private ManagerInterface $eventManager;

    /** @var NostoCategoryService */
    private NostoCategoryService $nostoCategoryService;

    /** @var NostoLogger */
    private NostoLogger $logger;

    /**
     * Builder constructor.
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ManagerInterface $eventManager
     * @param NostoCategoryService $nostoCategoryService
     * @param NostoLogger $logger
     */
    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        ManagerInterface $eventManager,
        NostoCategoryService $nostoCategoryService,
        NostoLogger $logger
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->eventManager = $eventManager;
        $this->nostoCategoryService = $nostoCategoryService;
        $this->logger = $logger;
    }

    /**
     * @param Category $category
     * @param Store $store
     * @return NostoCategory|null
     */
    public function build(Category $category, Store $store)
    {
        $nostoCategory = new NostoCategory();
        try {
            $nostoCategory->setId($category->getId());
            $nostoCategory->setParentId($category->getParentId());
            $nostoCategory->setTitle($this->getCategoryNameById($category->getId(), $store->getId()));
            $nostoCategory->setPath(
                $this->nostoCategoryService->getCategory($category, $store)
            );
            $nostoCategory->setUrl($category->getUrl());
        } catch (Exception $e) {
            $this->logger->exception($e);
        }
        if (empty($nostoCategory)) {
            $nostoCategory = null;
        } else {
            $this->eventManager->dispatch(
                'nosto_category_load_after',
                ['category' => $nostoCategory, 'magentoCategory' => $category]
            );
        }

        return $nostoCategory;
    }

    /**
     * @param int $id
     * @param null $storeId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCategoryNameById($id, $storeId = null)
    {
        $categoryInstance = $this->categoryRepository->get($id, $storeId);
        return $categoryInstance->getName();
    }

    /**
     * @param Category $category
     * @return bool
     */
    private function getCategoryVisibleInMenu(Category $category)
    {
        $visibleInMenu = $category->getIncludeInMenu();
        return $visibleInMenu === "1";
    }
}
