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

namespace Nosto\Tagging\Cron;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Product\Service as NostoProductService;
use Psr\Log\LoggerInterface;

/**
 * Cronjob class that periodically updates products to Nosto
 *
 * @package Nosto\Tagging\Cron
 */
class ProductPrices
{
    protected $logger;
    private $nostoProductService;
    private $nostoHelperScope;
    private $productRepository;
    private $searchCriteriaBuilder;

    /**
     * Rates constructor.
     *
     * @param LoggerInterface $logger
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoProductService $nostoProductService
     * @param ProductRepository $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        LoggerInterface $logger,
        NostoHelperScope $nostoHelperScope,
        NostoProductService $nostoProductService,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepository $productRepository
    ) {
        $this->logger = $logger;
        $this->nostoProductService = $nostoProductService;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function execute()
    {
        $this->logger->info('Synchronizing product prices to Nosto');
//        $searchCriteria = $this->searchCriteriaBuilder
//            ->addFilter('special_from_date', '2017-01-01', 'eq')
//            ->create();
//        $products = $this->productRepository->getList($searchCriteria)->getItems();
//        $this->nostoProductService->update($products);
        $this->logger->info('Product price synchronization finished');
    }

    protected function getProducts()
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('special_from_date', '2017-01-01', 'eq')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();

        return $products;
    }
}
