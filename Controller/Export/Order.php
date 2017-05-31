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

namespace Nosto\Tagging\Controller\Export;

use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Order\Collection as NostoOrderCollection;

/**
 * Order export controller used to export order history to Nosto in order to
 * bootstrap the recommendations during initial account creation.
 * This controller will be called by Nosto when a new account has been created
 * from the Magento backend. The controller is public, but the information is
 * encrypted with AES, and only Nosto can decrypt it.
 */
class Order extends Base
{
    private $orderCollectionFactory;
    private $nostoOrderCollection;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoOrderCollection $nostoOrderCollection
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        OrderCollectionFactory $orderCollectionFactory,
        NostoHelperScope $nostoHelperScope,
        NostoHelperAccount $nostoHelperAccount,
        NostoOrderCollection $nostoOrderCollection
    ) {
        parent::__construct($context, $nostoHelperScope, $nostoHelperAccount);
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->nostoOrderCollection = $nostoOrderCollection;
    }

    /**
     * @inheritdoc
     * @suppress PhanParamSignatureMismatch
     */
    public function buildExportCollection(Store $store, $limit = 100, $offset = 0)
    {
        return $this->nostoOrderCollection->buildMany($store, $limit, $offset);
    }

    /**
     * @inheritdoc
     * @suppress PhanParamSignatureMismatch
     */
    public function buildSingleExportCollection(Store $store, $id)
    {
        return $this->nostoOrderCollection->buildSingle($store, $id);
    }
}
