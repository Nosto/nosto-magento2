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

namespace Nosto\Tagging\Api\Data;

use DateTime;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Interface ProductUpdateQueueInterface
 */
interface ProductUpdateQueueInterface
{
    const ID = 'id';
    const CREATED_AT = 'created_at';
    const STARTED_AT = 'started_at';
    const COMPLETED_AT = 'completed_at';
    const STORE_ID = 'store_id';
    const PRODUCT_IDS = 'product_ids';
    const PRODUCT_ID_COUNT = 'product_id_count';
    const ACTION = 'action';
    const ACTION_VALUE_UPSERT = 'upsert';
    const ACTION_VALUE_DELETE = 'delete';
    const STATUS = 'status';
    const STATUS_VALUE_NEW = 'new';
    const STATUS_VALUE_PROCESSING = 'processing';
    const STATUS_VALUE_DONE = 'done';

    /**
     * Get row id
     *
     * @return int|null
     */
    public function getId();

    /**
     * Get created at time
     *
     * @return DateTime
     */
    public function getCreatedAt();

    /**
     * Get started at time
     *
     * @return DateTime
     */
    public function getStartedAt();

    /**
     * Get completed at time
     *
     * @return DateTime
     */
    public function getCompletedAt();

    /**
     * Get store id
     *
     * @return int
     */
    public function getStoreId();

    /**
     * Get product data
     *
     * @return string|null
     */
    public function getProductIds();

    /**
     * Get the queue status
     *
     * @return string
     */
    public function getStatus();

    /**
     * Get the queue action
     *
     * @return string
     */
    public function getAction();

    /**
     * Get the count of product ids in entry
     *
     * @return int
     */
    public function getProductIdCount();

    /**
     * Set id
     *
     * @param int $id
     * @return self
     * @SuppressWarnings(PHPMD.ShortVariable)
     */
    public function setId($id);

    /**
     * Set product id
     *
     * @param array $productIds
     * @return self
     */
    public function setProductIds(array $productIds);

    /**
     * Set store id
     *
     * @param int $storeId
     * @return self
     */
    public function setStoreId($storeId);

    /**
     * Set created at time
     *
     * @param DateTime $createdAt
     * @return self
     */
    public function setCreatedAt(DateTime $createdAt);

    /**
     * Set started at time
     *
     * @param DateTime $startedAt
     * @return self
     */
    public function setStartedAt(DateTime $startedAt);

    /**
     * Set completed at time
     *
     * @param DateTime $completedAt
     * @return self
     */
    public function setCompletedAt(DateTime $completedAt);

    /**
     * @param StoreInterface $store
     * @return self
     */
    public function setStore(StoreInterface $store);

    /**
     * @param string $status
     * @return self
     */
    public function setStatus($status);

    /**
     * @param string $action
     * @return self
     */
    public function setAction($action);

    /**
     * @param int $count
     * @return self
     */
    public function setProductIdCount($count);
}
