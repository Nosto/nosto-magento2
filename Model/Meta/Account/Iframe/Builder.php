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

namespace Nosto\Tagging\Model\Meta\Account\Iframe;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\Store;
use Nosto\NostoException;
use Nosto\Object\Iframe;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private $nostoHelperUrl;
    private $nostoHelperData;
    private $localeResolver;
    private $backendAuthSession;
    private $logger;
    private $eventManager;

    /**
     * @param NostoHelperUrl $nostoHelperUrl
     * @param NostoHelperData $nostoHelperData
     * @param Session $backendAuthSession
     * @param ResolverInterface $localeResolver
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        NostoHelperUrl $nostoHelperUrl,
        NostoHelperData $nostoHelperData,
        Session $backendAuthSession,
        ResolverInterface $localeResolver,
        NostoLogger $logger,
        ManagerInterface $eventManager
    ) {
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->nostoHelperData = $nostoHelperData;
        $this->backendAuthSession = $backendAuthSession;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Store $store
     * @return Iframe
     * @throws LocalizedException
     */
    public function build(Store $store)
    {
        $metaData = new Iframe();

        try {
            $metaData->setUniqueId($this->nostoHelperData->getInstallationId());
            $lang = substr($this->localeResolver->getLocale(), 0, 2);
            $metaData->setLanguageIsoCode($lang);
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setLanguageIsoCodeShop($lang);
            if ($this->backendAuthSession->getUser()) {
                $metaData->setEmail($this->backendAuthSession->getUser()->getEmail());
            } else {
                throw new NostoException('Could not get user from Backend Auth Session');
            }
            $metaData->setPlatform('magento');
            $metaData->setShopName($store->getName());
            $metaData->setUniqueId($this->nostoHelperData->getInstallationId());
            $metaData->setVersionPlatform($this->nostoHelperData->getPlatformVersion());
            $metaData->setVersionModule($this->nostoHelperData->getModuleVersion());
            $metaData->setPreviewUrlProduct($this->nostoHelperUrl->getPreviewUrlProduct($store));
            $metaData->setPreviewUrlCategory($this->nostoHelperUrl->getPreviewUrlCategory($store));
            $metaData->setPreviewUrlSearch($this->nostoHelperUrl->getPreviewUrlSearch($store));
            $metaData->setPreviewUrlCart($this->nostoHelperUrl->getPreviewUrlCart($store));
            $metaData->setPreviewUrlFront($this->nostoHelperUrl->getPreviewUrlFront($store));
        } catch (NostoException $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_iframe_load_after', ['iframe' => $metaData]);

        return $metaData;
    }
}
