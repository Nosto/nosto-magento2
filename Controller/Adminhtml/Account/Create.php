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
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\LocalizedException;
use Nosto\Helper\IframeHelper;
use Nosto\Nosto;
use Nosto\NostoException;
use Nosto\Operation\AccountSignup;
use Nosto\Tagging\Helper\Cache as NostoHelperCache;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Currency as NostoCurrencyHelper;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Meta\Account\Builder as NostoSignupBuilder;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use Nosto\Tagging\Model\Meta\Account\Owner\Builder as NostoOwnerBuilder;
use Nosto\Tagging\Model\Rates\Service as NostoRatesService;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Create extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';
    private $result;
    private $nostoHelperAccount;
    private $nostoCurrentUserBuilder;
    private $nostoIframeMetaBuilder;
    private $nostoRatesService;
    private $nostoCurrencyHelper;
    private $nostoOwnerBuilder;
    private $nostoSignupBuilder;
    private $logger;
    private $nostoHelperScope;
    private $nostoHelperCache;

    /**
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoSignupBuilder $nostoSignupBuilder
     * @param NostoIframeMetaBuilder $nostoIframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param NostoOwnerBuilder $nostoOwnerBuilder
     * @param NostoHelperScope $nostoHelperScope
     * @param Json $result
     * @param NostoLogger $logger
     * @param NostoRatesService $nostoRatesService
     * @param NostoCurrencyHelper $nostoCurrencyHelper
     * @param NostoHelperCache $nostoHelperCache
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoSignupBuilder $nostoSignupBuilder,
        NostoIframeMetaBuilder $nostoIframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        NostoOwnerBuilder $nostoOwnerBuilder,
        NostoHelperScope $nostoHelperScope,
        Json $result,
        NostoLogger $logger,
        NostoRatesService $nostoRatesService,
        NostoCurrencyHelper $nostoCurrencyHelper,
        NostoHelperCache $nostoHelperCache
    ) {
        parent::__construct($context);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoSignupBuilder = $nostoSignupBuilder;
        $this->nostoIframeMetaBuilder = $nostoIframeMetaBuilder;
        $this->nostoOwnerBuilder = $nostoOwnerBuilder;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
        $this->result = $result;
        $this->logger = $logger;
        $this->nostoRatesService = $nostoRatesService;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
        $this->nostoHelperScope = $nostoHelperScope;
        $this->nostoHelperCache = $nostoHelperCache;
    }

    /**
     * @return Json
     * @throws LocalizedException
     * @throws \Zend_Validate_Exception
     * @suppress PhanTypeMismatchArgument
     * @SuppressWarnings(PHPMD.CyclomaticComplexity
     * @throws \Zend_Validate_Exception
     */
    public function execute()
    {
        $response = ['success' => false];

        $storeId = $this->_request->getParam('store');
        $store = $this->nostoHelperScope->getStore($storeId);
        $messageText = null;
        if ($store !== null) {
            try {
                $signupDetails = $this->_request->getParam('details');
                if (!empty($signupDetails)) {
                    $signupDetails = json_decode($signupDetails, true);
                }

                $emailAddress = $this->_request->getParam('email');
                $accountOwner = $this->nostoOwnerBuilder->build();
                if ($accountOwner->getEmail() !== $emailAddress) {
                    if (\Zend_Validate::is($emailAddress, 'EmailAddress')) {
                        $accountOwner->setFirstName(null);
                        $accountOwner->setLastName(null);
                        $accountOwner->setEmail($emailAddress);
                    } else {
                        throw new NostoException('Invalid email address ' . $emailAddress);
                    }
                }

                $signupParams = $this->nostoSignupBuilder->build(
                    $store,
                    $accountOwner,
                    $signupDetails
                );
                $operation = new AccountSignup($signupParams);
                $account = $operation->create();

                if ($this->nostoHelperAccount->saveAccount($account, $store)) {
                    $response['success'] = true;
                    $response['redirect_url'] = IframeHelper::getUrl(
                        $this->nostoIframeMetaBuilder->build($store),
                        $account,
                        $this->nostoCurrentUserBuilder->build(),
                        [
                            'message_type' => Nosto::TYPE_SUCCESS,
                            'message_code' => Nosto::CODE_ACCOUNT_CREATE,
                        ]
                    );

                    // Note that we will send the exhange rates even if the multi currency
                    // is not set. This is mostly for debugging purposes.
                    if ($this->nostoCurrencyHelper->getCurrencyCount($store) > 1) {
                        try {
                            $this->nostoRatesService->update($store);
                        } catch (\Exception $e) {
                            $this->logger->exception($e);
                        }
                    }

                    //invalidate page cache and layout cache
                    $this->nostoHelperCache->invalidatePageCache();
                    $this->nostoHelperCache->invalidateLayoutCache();
                }
            } catch (NostoException $e) {
                $this->logger->exception($e);
                $messageText = $e->getMessage();
            }
        }

        if (!$response['success']) {
            $params = [
                'message_type' => Nosto::TYPE_ERROR,
                'message_code' => Nosto::CODE_ACCOUNT_CREATE,
            ];
            if ($messageText) {
                $params['message_text'] = $messageText;
            }
            $response['redirect_url'] = IframeHelper::getUrl(
                $this->nostoIframeMetaBuilder->build($store),
                null, // account creation failed, so we have none.
                $this->nostoCurrentUserBuilder->build(),
                $params
            );
        }

        return $this->result->setData($response);
    }
}
