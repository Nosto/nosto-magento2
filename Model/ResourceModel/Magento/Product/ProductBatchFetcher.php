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

namespace Nosto\Tagging\Model\ResourceModel\Magento\Product;

use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Nosto\Tagging\Helper\Account as NostoAccountHelper;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Nosto\Tagging\Model\Service\AbstractService;

class ProductBatchFetcher extends AbstractService
{
    public const BATCH_SIZE = 500;
    public const BENCHMARK_SYNC_NAME = 'nosto_product_queue_insert';
    public const BENCHMARK_SYNC_BREAKPOINT = 1;

    /** @var ResourceConnection */
    protected $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection,
        NostoLogger $logger,
        NostoDataHelper $nostoDataHelper,
        NostoAccountHelper $nostoAccountHelper
    ) {
        parent::__construct($nostoDataHelper, $nostoAccountHelper, $logger);
        $this->resourceConnection = $resourceConnection;
    }

    public function fetchProductIdBatches()
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('catalog_product_entity');
        $visibilityTable = $connection->getTableName('catalog_product_entity_int');
        $statusTable = $connection->getTableName('catalog_product_entity_int');

        $offset = 0;
        $this->startBenchmark(self::BENCHMARK_SYNC_NAME, self::BENCHMARK_SYNC_BREAKPOINT);
        $visibility = $this->getAttributeId('visibility');
        $status = $this->getAttributeId('status');
        do {
            $this->checkMemoryConsumption('indexer by ID product sync');
            $query = $connection->select()
                ->from(['e' => $tableName], ['entity_id'])
                ->join(['visibility' => $visibilityTable], 'e.entity_id = visibility.entity_id', [])
                ->join(['status' => $statusTable], 'e.entity_id = status.entity_id', [])
                ->where('visibility.attribute_id = ?', $visibility)
                ->where('status.attribute_id = ?', $status) // @TODO: abstract those like in the collections
                ->where('visibility.value != ?', ProductVisibility::VISIBILITY_NOT_VISIBLE)
                ->where('status.value = ?', Status::STATUS_ENABLED)
                ->limit(self::BATCH_SIZE, $offset);

            $results = $connection->fetchAll($query);

            if (count($results) === 0) {
                break;
            }

            $productIdsBatch = [];
            foreach ($results as $row) {
                $productIdsBatch[] = (int) $row['entity_id'];
            }

            yield $productIdsBatch;
            $this->tickBenchmark(self::BENCHMARK_SYNC_NAME, true);
            $offset += self::BATCH_SIZE;
        } while (count($results) > 0);
    }

    /**
     * @param string $attributeCode
     * @return string
     */
    protected function getAttributeId(string $attributeCode): string
    {
        $connection = $this->resourceConnection->getConnection();
        $attributeTable = $connection->getTableName('eav_attribute');

        $query = $connection->select()
            ->from($attributeTable, 'attribute_id')
            ->where('attribute_code = ?', $attributeCode)
            ->where('entity_type_id = ?', $this->getEntityTypeId('catalog_product'));

        return $connection->fetchOne($query);
    }

    /**
     * @param string $entityTypeCode
     * @return string
     */
    protected function getEntityTypeId(string $entityTypeCode): string
    {
        $connection = $this->resourceConnection->getConnection();
        $entityTypeTable = $connection->getTableName('eav_entity_type');

        $query = $connection->select()
            ->from($entityTypeTable, 'entity_type_id')
            ->where('entity_type_code = ?', $entityTypeCode);

        return $connection->fetchOne($query);
    }
}
