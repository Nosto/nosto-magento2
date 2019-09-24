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

namespace Nosto\Tagging\Model\Indexer;

use Exception;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;
use Nosto\Tagging\Model\Service\Index as NostoIndexService;
use Nosto\Exception\MemoryOutOfBoundsException;
use Nosto\Tagging\Model\Indexer\Data\ModeSwitcher as DataModeSwitcher;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Indexer\Model\ProcessManager;

/**
 * An indexer for Nosto product sync
 */
class Data extends ParallelIndexer
{
    const INDEXER_ID = 'nosto_index_product_data_sync';

    /** @var NostoIndexService */
    private $nostoServiceIndex;

    /** @var DataModeSwitcher */
    private $modeSwitcher;

    /**
     * Data constructor.
     * @param NostoIndexService $nostoServiceIndex
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param DataModeSwitcher $dataModeSwitcher
     * @param NostoLogger $logger
     * @param ProcessManager $processManager
     */
    public function __construct(
        NostoIndexService $nostoServiceIndex,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        DataModeSwitcher $dataModeSwitcher,
        NostoLogger $logger,
        ProcessManager $processManager
    ) {
        $this->nostoServiceIndex = $nostoServiceIndex;
        $this->modeSwitcher = $dataModeSwitcher;
        parent::__construct(
            $nostoHelperAccount,
            $nostoHelperScope,
            $logger,
            $processManager
        );
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeFull()
    {
        $this->doWork();
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function execute($ids)
    {
        $this->doWork($ids);
    }

    /**
     * @inheritdoc
     */
    public function getModeSwitcher(): ModeSwitcherInterface
    {
        return $this->modeSwitcher;
    }

    /**
     * @inheritdoc
     */
    public function getIndexerId(): string
    {
        return self::INDEXER_ID;
    }

    /**
     * @inheritdoc
     */
    public function doIndex(Store $store, array $ids = [])
    {
        try {
            $this->nostoServiceIndex->indexProducts($store, $ids);
            // Catch only MemoryOutOfBoundsException as this is the most expected ones
            // And the ones we are interested of
        } catch (MemoryOutOfBoundsException $e) {
            $this->nostoLogger->error($e->getMessage());
        } catch (NostoException $e) {
            $this->nostoLogger->error($e->getMessage());
        }
    }
}
