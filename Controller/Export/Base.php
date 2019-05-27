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

namespace Nosto\Tagging\Controller\Export;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\Store;
use Nosto\Helper\ExportHelper;
use Nosto\Object\AbstractCollection;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;

/**
 * Export base controller that all export controllers must extend.
 */
abstract class Base extends Action
{
    const ID = 'id';
    const LIMIT = 'limit';
    const OFFSET = 'offset';

    private $nostoHelperAccount;
    private $nostoHelperScope;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperAccount $nostoHelperAccount
     */
    public function __construct(
        Context $context,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount
    ) {
        parent::__construct($context);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * Handles the controller request, builds the query to fetch the result,
     * encrypts the JSON and returns the result
     *
     * @return Raw
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $store = $this->nostoHelperScope->getStore(true);
        $id = $this->getRequest()->getParam(self::ID, false);
        if (!empty($id)) {
            return $this->export($this->buildSingleExportCollection($store, $id));
        }
        $pageSize = (int)$this->getRequest()->getParam(self::LIMIT, 100);
        $currentOffset = (int)$this->getRequest()->getParam(self::OFFSET, 0);
        return $this->export($this->buildExportCollection($store, $pageSize, $currentOffset));
    }

    /**
     * Abstract function that should be implemented to return the correct collection object with
     * the controller specific filters applied
     *
     * @param Store $store The store object for the current store
     * @param $id
     * @return AbstractCollection The collection
     */
    abstract public function buildSingleExportCollection(Store $store, $id);

    /**
     * Abstract function that should be implemented to return the built export collection object
     * with all the items added
     *
     * @param Store $store
     * @param int $limit
     * @param int $offset
     * @return AbstractCollection the collection with the items to export
     */
    abstract public function buildExportCollection(Store $store, $limit = 100, $offset = 0);

    /**
     * Encrypts the export collection and outputs it to the browser.
     *
     * @param AbstractCollection $collection the data collection to export.
     * @return Raw
     */
    public function export(AbstractCollection $collection)
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $store = $this->nostoHelperScope->getStore(true);
        $account = $this->nostoHelperAccount->findAccount($store);
        if ($account !== null) {
            $cipherText = (new ExportHelper())->export($account, $collection);
            if ($result instanceof Raw) {
                $result->setContents($cipherText);
            }
        }
        return $result;
    }
}
