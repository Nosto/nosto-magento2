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

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Redirect;
use Nosto\Helper\OAuthHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Meta\Oauth\Builder as NostoOauthBuilder;

class Connect extends Base
{
    public const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';
    private NostoOauthBuilder $oauthMetaBuilder;
    private NostoHelperScope $nostoHelperScope;

    /**
     * @param Context $context
     * @param NostoOauthBuilder $oauthMetaBuilder
     * @param NostoHelperScope $nostoHelperScope
     */
    public function __construct(
        Context $context,
        NostoOauthBuilder $oauthMetaBuilder,
        NostoHelperScope $nostoHelperScope
    ) {
        parent::__construct($context);

        $this->oauthMetaBuilder = $oauthMetaBuilder;
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @return Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $storeId = $this->_request->getParam('store');
        $store = $this->nostoHelperScope->getStore($storeId);

        if ($store !== null) {
            $metaData = $this->oauthMetaBuilder->build($store);
            $this->getMessageManager()->addSuccessMessage(
                "Store was successfully connected to the existing Nosto account."
            );

            return $resultRedirect->setUrl(OAuthHelper::getAuthorizationUrl($metaData));
        }
    }
}
