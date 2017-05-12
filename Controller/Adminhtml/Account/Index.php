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

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Website;
use Nosto\Tagging\Helper\Store as NostoHelperStore;

class Index extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';
    private $resultPageFactory;
    private $nostoHelperStore;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param NostoHelperStore $nostoHelperStore
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        NostoHelperStore $nostoHelperStore
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->nostoHelperStore = $nostoHelperStore;
    }

    /**
     * @return Page | Redirect
     */
    public function execute()
    {
        if (!$this->getSelectedStore()) {
            // If we are not under a store view, then redirect to the first
            // found one. Nosto is configured per store.
            /** @var Website $website */
            foreach ($this->nostoHelperStore->getWebsites() as $website) {
                /** @noinspection PhpUndefinedMethodInspection */
                $storeId = $website->getDefaultGroup()->getDefaultStoreId();
                if (!empty($storeId)) {
                    return $this->resultRedirectFactory->create()
                        ->setPath('*/*/index', ['store' => $storeId]);
                }
            }
        }

        $result = $this->resultPageFactory->create();
        if ($result instanceof Page) {
            $result->setActiveMenu(self::ADMIN_RESOURCE);
            $result->getConfig()->getTitle()->prepend(__('Nosto - Account Settings'));
        }

        return $result;
    }

    /**
     * Returns the currently selected store.
     * If it is single store setup, then just return the default store.
     * If it is a multi store setup, the expect a store id to passed in the
     * request params and return that store as the current one.
     *
     * @return StoreInterface|null the store or null if not found.
     */
    private function getSelectedStore()
    {
        $store = null;
        if ($this->nostoHelperStore->isSingleStoreMode()) {
            $store = $this->nostoHelperStore->getStore(true);
        } elseif (($storeId = $this->nostoHelperStore->getStore()->getId())) {
            $store = $this->nostoHelperStore->getStore($storeId);
        }

        return $store;
    }
}
