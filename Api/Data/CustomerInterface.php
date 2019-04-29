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

namespace Nosto\Tagging\Api\Data;

interface CustomerInterface
{
    const CUSTOMER_ID = 'customer_id';
    const QUOTE_ID = 'quote_id';
    const NOSTO_ID = 'nosto_id';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    const RESTORE_CART_HASH = 'restore_cart_hash';

    /**
     * @var int The length of the restore cart attribute
     */
    const NOSTO_TAGGING_RESTORE_CART_ATTRIBUTE_LENGTH = 64;

    /**
     * Get customer id
     *
     * @return int|null
     */
    public function getCustomerId();

    /**
     * Get quote id
     *
     * @return int|null
     */
    public function getQuoteId();

    /**
     * Get Nosto Id
     *
     * @return string
     */
    public function getNostoId();

    /**
     * Get created at time
     *
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * Get updated at time
     *
     * @return \DateTime
     */
    public function getUpdatedAt();

    /**
     * Get restore cart hash
     *
     * @return string restore cart hash
     */
    public function getRestoreCartHash();

    /**
     * Set customer id
     *
     * @param int $customerId
     * @return self
     */
    public function setCustomerId($customerId);

    /**
     * Set quote id
     *
     * @param int $quoteId
     */
    public function setQuoteId($quoteId);

    /**
     * Set Nosto Id
     *
     * @param string $nostoId
     * @return self
     */
    public function setNostoId($nostoId);

    /**
     * Set created at time
     *
     * @param \DateTime $createdAt
     * @return self
     */
    public function setCreatedAt(\DateTime $createdAt);

    /**
     * Set updated at time
     *
     * @param \DateTime $updatedAt
     * @return self
     */
    public function setUpdatedAt(\DateTime $updatedAt);

    /**
     * Set restore cart hash
     *
     * @param string $restoreCartHash
     * @return self
     */
    public function setRestoreCartHash($restoreCartHash);
}
