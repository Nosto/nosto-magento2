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

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Nosto\Nosto;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Cache as NostoHelperCache;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;

class Delete extends Base
{
    public const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';
    private NostoHelperAccount $nostoHelperAccount;
    private NostoCurrentUserBuilder $nostoCurrentUserBuilder;
    private NostoHelperScope $nostoHelperScope;
    private NostoHelperCache $nostoHelperCache;

    /**
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperCache $nostoHelperCache
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        NostoHelperScope $nostoHelperScope,
        NostoHelperCache $nostoHelperCache
    ) {
        parent::__construct($context);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperCache = $nostoHelperCache;
    }

    /**
     * @return Redirect
     * @suppress PhanUndeclaredMethod
     * @throws Exception
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $storeId = $this->_request->getParam('store');
        $store = $this->nostoHelperScope->getStore($storeId);

        if ($store === null) {
            throw new LocalizedException(new Phrase('No account found'));
        }

        $account = $this->nostoHelperAccount->findAccount($store);

        if ($account !== null) {
            $currentUser = $this->nostoCurrentUserBuilder->build();
            if ($this->nostoHelperAccount->deleteAccount($account, $store, $currentUser)) {
                //Invalidate the cache
                $this->nostoHelperCache->invalidatePageCache();
                $this->nostoHelperCache->invalidateLayoutCache();

                $this->getMessageManager()->addSuccess(__("Nosto has been successfully disconnected from the store."));
                return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
            }
        }

        return $resultRedirect->setUrl($this->_redirect->getRefererUrl());
    }
}
