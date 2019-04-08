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

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Phrase;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;

class Scope extends AbstractHelper
{
    private $storeManager;

    /**
     * Scope constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
    }

    /**
     * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
     * @return \Magento\Store\Model\Store
     */
    public function getStore($storeId = null)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->storeManager->getStore($storeId);
    }

    /**
     * @param bool $withDefault
     * @param bool $codeKey
     * @return \Magento\Store\Model\Store[]
     */
    public function getStores($withDefault = false, $codeKey = false)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->storeManager->getStores($withDefault, $codeKey);
    }

    /**
     * Return the store by store code
     *
     * @param $scopeCode
     * @return mixed
     */
    public function getStoreByCode($scopeCode)
    {
        $stores = $this->getStores();
        foreach ($stores as $store) {
            if ($store->getCode() === $scopeCode) {
                return $store;
            }
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isSingleStoreMode()
    {
        return $this->storeManager->isSingleStoreMode();
    }

    /**
     * Get loaded websites
     *
     * @param bool $withDefault
     * @param bool $codeKey
     * @return Website[]
     */
    public function getWebsites($withDefault = false, $codeKey = false)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->storeManager->getWebsites($withDefault, $codeKey);
    }

    /**
     * Returns the currently selected store.
     * If it is single store setup, then just return the default store.
     * If it is a multi store setup, the expect a store id to passed in the
     * request params and return that store as the current one.
     *
     * @param RequestInterface $request
     * @return Store the store or null if not found.
     * @throws NotFoundException
     */
    public function getSelectedStore(RequestInterface $request)
    {
        $store = null;
        if ($this->isSingleStoreMode()) {
            $store = $this->getStore(true);
        } elseif ($storeId = $request->getParam('store')) {
            $store = $this->getStore($storeId);
        } elseif ($this->getStore()) {
            $store = $this->getStore();
        } else {
            throw new NotFoundException(new Phrase('Store not found.'));
        }

        return $store;
    }
}
