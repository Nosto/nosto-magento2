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
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;
use NostoHelperIframe;
use NostoMessage;

class Delete extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $result;
    private $storeManager;
    private $nostoHelperAccount;
    private $nostoCurrentUserBuilder;
    private $nostoIframeMetaBuilder;

    /**
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoIframeMetaBuilder $nostoIframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoIframeMetaBuilder $nostoIframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        StoreManagerInterface $storeManager,
        Json $result
    ) {
        parent::__construct($context);

        $this->nostoIframeMetaBuilder = $nostoIframeMetaBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->storeManager = $storeManager;
        $this->result = $result;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $response = ['success' => false];

        $storeId = $this->_request->getParam('store');
        /** @var Store $store */
        $store = $this->storeManager->getStore($storeId);
        $account = !is_null($store)
            ? $this->nostoHelperAccount->findAccount($store)
            : null;

        if (!is_null($store) && !is_null($account)) {
            $currentUser = $this->nostoCurrentUserBuilder->build();
            if ($this->nostoHelperAccount->deleteAccount($account, $store, $currentUser)) {
                $response['success'] = true;
                $response['redirect_url'] = NostoHelperIframe::getUrl(
                    $this->nostoIframeMetaBuilder->build($store),
                    null, // we don't have an account anymore
                    $this->nostoCurrentUserBuilder->build(),
                    [
                        'message_type' => NostoMessage::TYPE_SUCCESS,
                        'message_code' => NostoMessage::CODE_ACCOUNT_DELETE,
                    ]
                );
            }
        }

        if (!$response['success']) {
            $response['redirect_url'] = NostoHelperIframe::getUrl(
                $this->nostoIframeMetaBuilder->build($store),
                $account,
                $this->nostoCurrentUserBuilder->build(),
                [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_DELETE,
                ]
            );
        }

        return $this->result->setData($response);
    }
}
