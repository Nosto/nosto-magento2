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

namespace Nosto\Tagging\Model\Filter;

use Magento\Backend\Model\Auth\Session;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\CatalogSearch\Model\Layer\Filter\Category as CategoryFilter;
use Magento\CatalogSearch\Model\Layer\Filter\Price;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Helper\Currency as NostoHelperCurrency;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Filter as NostoFilter;

class Builder
{
    const NOSTO_PRICE_FROM = 'nosto_price_from';
    const NOSTO_PRICE_TO = 'nosto_price_to';

    private $logger;
    private $backendAuthSession;
    private $eventManager;
    private $categoryBuilder;
    private $categoryRepository;
    private $store;
    private $nostoHelperCurrency;

    /**
     * @param Session $backendAuthSession
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param NostoCategoryBuilder $categoryBuilder
     * @param CategoryRepositoryInterface $categoryRepository
     * @param NostoHelperCurrency $nostoHelperCurrency
     * @param Store $store
     */
    public function __construct(
        Session $backendAuthSession,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        NostoCategoryBuilder $categoryBuilder,
        CategoryRepositoryInterface $categoryRepository,
        NostoHelperCurrency $nostoHelperCurrency,
        Store $store
    ) {
        $this->backendAuthSession = $backendAuthSession;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->categoryBuilder = $categoryBuilder;
        $this->categoryRepository = $categoryRepository;
        $this->nostoHelperCurrency = $nostoHelperCurrency;
        $this->store = $store;
    }

    /**
     * @return NostoFilter
     */
    public function build($filters)
    {
        $nostoFilter = new NostoFilter();
        $validFilters = array();
        $categoryFilters = array();
        try {
            /** @var \Magento\Catalog\Model\Layer\Filter\Item $filter */
            foreach ($filters as $filter) {
                $model = $filter->getFilter();
                if ($model instanceof Price) {
                    $this->loadPriceRange($filter, $nostoFilter);
                    continue;
                }

                if ($model instanceof CategoryFilter) {
                    $categoryFilters[] = $this->loadCategoryFilter($filter);
                    continue;
                }
                if ($model
                    && $model->getAttributeModel()
                    && $model->getAttributeModel()->getAttributeCode()
                ) {
                    $validFilters[$model->getAttributeModel()->getAttributeCode()] = $filter->getLabel();
                }
            }
            $nostoFilter->setCustomFieldsFilter($validFilters);
            $nostoFilter->setCategoriesFilter($categoryFilters);
        } catch (NostoException $e) {
            $this->logger->exception($e);
        }
        $this->eventManager->dispatch('nosto_filter_load_after', [
            'filter' => $nostoFilter,
            'magentoFilters' => $filters
        ]);

        return $nostoFilter;
    }

    protected function loadPriceRange(Item $filter, NostoFilter $nostoFilter)
    {
        $data = $filter->getData();
        if ($data && array_key_exists('value', $data)) {
            $value = $data['value'];
            if (is_array($value)) {
                $priceRangeFilter = array();
                if (array_key_exists(0, $value) && $value[0] !== '') {
                    $priceRangeFilter[self::NOSTO_PRICE_FROM] = $value[0];
                }
                if (array_key_exists(1, $value) && $value[1] !== '') {
                    $priceRangeFilter[self::NOSTO_PRICE_TO] = $value[1];
                }

                //Always tag the price filter in base currency if multi-currency is enabled
                //because it is the currency to be store in the nosto
                $priceRangeFilter = array_map(function ($price) {
                    return $this->nostoHelperCurrency->convertToBaseCurrency($price, $this->store);
                }, $priceRangeFilter);
                $nostoFilter->setPriceRangeFilter($priceRangeFilter);
            }
        }
    }

    protected function loadCategoryFilter(Item $filter)
    {
        $categoryId = $filter->getValueString();
        if ($categoryId) {
            try {
                $category = $this->categoryRepository->get((int)$categoryId);
                if ($category instanceof CategoryModel) {
                    return $this->categoryBuilder->build($category);
                }
            } catch (NoSuchEntityException $noSuchEntityException) {
                return null;
            }
        }

        return null;
    }
}
