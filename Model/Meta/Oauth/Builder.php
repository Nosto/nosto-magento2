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

namespace Nosto\Tagging\Model\Meta\Oauth;

use Exception;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use Nosto\OAuth;
use Nosto\Model\Signup\Account;
use Nosto\Request\Api\Token;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private ResolverInterface $localeResolver;
    private Url $urlBuilder;
    private NostoLogger $logger;
    private ManagerInterface $eventManager;
    private NostoHelperData $nostoHelperData;

    /**
     * @param ResolverInterface $localeResolver
     * @param Url $urlBuilder
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        ResolverInterface $localeResolver,
        Url $urlBuilder,
        NostoLogger $logger,
        ManagerInterface $eventManager,
        NostoHelperData $nostoHelperData
    ) {
        $this->localeResolver = $localeResolver;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * @param Store $store
     * @param Account|null $account
     * @return OAuth
     */
    public function build(Store $store, Account $account = null)
    {
        $metaData = new OAuth();

        try {
            $metaData->setScopes(Token::getApiTokenNames());
            $redirectUrl = $this->urlBuilder->getUrl(
                'nosto/oauth',
                [
                    '_nosid' => true,
                    '_scope_to_url' => $this->nostoHelperData->getStoreCodeToUrl($store),
                    '_scope' => $store->getCode(),
                    '_query' => ['___store' => $store->getCode()]
                ]
            );
            $metaData->setClientId('magento');
            $metaData->setClientSecret('magento');
            $metaData->setRedirectUrl($redirectUrl);
            $lang = substr($this->localeResolver->getLocale(), 0, 2);
            $metaData->setLanguageIsoCode($lang);
            if ($account !== null) {
                $metaData->setAccount($account);
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_oauth_load_after', ['oauth' => $metaData]);

        return $metaData;
    }
}
