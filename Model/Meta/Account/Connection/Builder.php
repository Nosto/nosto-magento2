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

namespace Nosto\Tagging\Model\Meta\Account\Connection;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Model\ConnectionMetadata;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private NostoHelperData $nostoHelperData;
    private ResolverInterface $localeResolver;
    private Session $backendAuthSession;
    private NostoLogger $logger;
    private ManagerInterface $eventManager;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param Session $backendAuthSession
     * @param ResolverInterface $localeResolver
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        Session $backendAuthSession,
        ResolverInterface $localeResolver,
        NostoLogger $logger,
        ManagerInterface $eventManager
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->backendAuthSession = $backendAuthSession;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Store $store
     * @return ConnectionMetadata
     */
    public function build(Store $store)
    {
        $metaData = new ConnectionMetadata();
        $lang = substr($this->localeResolver->getLocale(), 0, 2);
        $metaData->setLanguageIsoCode($lang);
        $lang = substr($store->getConfig('general/locale/code'), 0, 2);
        $metaData->setLanguageIsoCodeShop($lang);
        if ($this->backendAuthSession->getUser()) {
            $metaData->setEmail($this->backendAuthSession->getUser()->getEmail());
        } else {
            $this->logger->exception(new NostoException('Could not get user from Backend Auth Session'));
        }
        $metaData->setPlatform('magento');
        $metaData->setShopName($store->getName());
        $metaData->setVersionPlatform($this->nostoHelperData->getPlatformVersion());
        $metaData->setVersionModule($this->nostoHelperData->getModuleVersion());

        $this->eventManager->dispatch('nosto_connection_load_after', ['connection' => $metaData]);

        return $metaData;
    }
}
