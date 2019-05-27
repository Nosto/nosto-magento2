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

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Result\PageFactory;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;

class Index extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';
    private $resultPageFactory;
    private $nostoHelperScope;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param NostoHelperScope $nostoHelperScope
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        NostoHelperScope $nostoHelperScope
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * @return Page | Redirect
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function execute()
    {
        $store = $this->nostoHelperScope->getSelectedStore($this->getRequest());

        if (!($store && $store->getId())) {
            // If we are not under a store view, then redirect to the first
            // found one. Nosto is configured per store.
            foreach ($this->nostoHelperScope->getWebsites() as $website) {
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
            $result->getConfig()->getTitle()->prepend(new Phrase('Nosto - Account Settings'));
        }

        return $result;
    }
}
