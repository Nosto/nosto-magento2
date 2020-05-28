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

namespace Nosto\Tagging\Model\Service\Sync\Delete;

use Exception;
use Magento\Store\Model\Store;
use Nosto\Model\Signup\Account as NostoSignupAccount;
use Nosto\NostoException;
use Nosto\Operation\DeleteProduct;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;
use Nosto\Tagging\Model\Service\AbstractService;
use Nosto\Tagging\Model\Service\Cache\CacheService;

class DeleteService extends AbstractService
{

    const BENCHMARK_DELETE_NAME = 'nosto_product_delete';
    const BENCHMARK_DELETE_BREAKPOINT = 1;
    const PRODUCT_DELETION_BATCH_SIZE = 100;

    /** @var CacheService */
    private $cacheService;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var int */
    private $deleteBatchSize;

    /**
     * DeleteService constructor.
     * @param CacheService $cacheService
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoLogger $logger
     * @param $deleteBatchSize
     */
    public function __construct(
        CacheService $cacheService,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperData $nostoHelperData,
        NostoHelperUrl $nostoHelperUrl,
        NostoLogger $logger,
        $deleteBatchSize
    ) {
        $this->cacheService = $cacheService;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->deleteBatchSize = $deleteBatchSize;
        parent::__construct($nostoHelperData, $nostoHelperAccount, $logger);
    }

    /**
     * Discontinues products in Nosto and removes indexed products from Nosto product index
     *
     * @param array $productIds
     * @param Store $store
     * @throws NostoException
     */
    public function delete(array $productIds, Store $store)
    {
        if (count($productIds) === 0) {
            return;
        }
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account instanceof NostoSignupAccount === false) {
            throw new NostoException(sprintf('Store view %s does not have Nosto installed', $store->getName()));
        }
        $this->startBenchmark(self::BENCHMARK_DELETE_NAME, self::BENCHMARK_DELETE_BREAKPOINT);
        $productIdBatches = array_chunk($productIds, $this->deleteBatchSize);
        $this->logDebugWithStore(
            sprintf(
                'Deleting total of %d products in batches of %d',
                count($productIds),
                count($productIdBatches)
            ),
            $store
        );
        foreach ($productIdBatches as $ids) {
            try {
                $op = new DeleteProduct($account, $this->nostoHelperUrl->getActiveDomain($store));
                $op->setResponseTimeout(30);
                $op->setProductIds($ids);
                $op->delete(); // @codingStandardsIgnoreLine
                $this->cacheService->removeByProductIds($store, $ids);
                $this->tickBenchmark(self::BENCHMARK_DELETE_NAME);
            } catch (Exception $e) {
                $this->getLogger()->exception($e);
            }
        }
        $this->logBenchmarkSummary(self::BENCHMARK_DELETE_NAME, $store);
    }
}
