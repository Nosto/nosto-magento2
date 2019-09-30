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

namespace Nosto\Tagging\Model\Service\Sync;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Framework\Module\Manager;
use Nosto\Tagging\Model\ResourceModel\Product\Index\Collection as NostoIndexCollection;

class CompatibilityBulkSync implements BulkSyncInterface
{
    /** @var AsyncBulkSync */
    private $asyncBulkSync;

    /** @var DirectBulkSync */
    private $directBulkSync;

    /** @var Manager */
    private $manager;

    /**
     * CompatibilityBulkSync constructor.
     * @param AsyncBulkSync $asyncBulkSync
     * @param DirectBulkSync $directBulkSync
     * @param Manager $manager
     */
    public function __construct(
        AsyncBulkSync $asyncBulkSync,
        DirectBulkSync $directBulkSync,
        Manager $manager
    ) {
        $this->asyncBulkSync = $asyncBulkSync;
        $this->directBulkSync = $directBulkSync;
        $this->manager = $manager;
    }

    /**
     * @param NostoIndexCollection $collection
     * @param Store $store
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(NostoIndexCollection $collection, Store $store)
    {
        if ($this->canUseBulkOperations()) {
            $this->asyncBulkSync->execute($collection, $store);
        } else {
            $this->directBulkSync->execute($collection, $store);
        }
    }

    /**
     * @return bool
     */
    private function canUseBulkOperations()
    {
        if ($this->manager->isEnabled('Magento_AsynchronousOperations')) {
            return true;
        }
        return false;
    }
}