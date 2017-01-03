<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Model\Meta\Oauth;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Url;
use Magento\Store\Model\Store;
use NostoAccount;
use NostoOAuth;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @param ResolverInterface $localeResolver
     * @param Url $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResolverInterface $localeResolver,
        Url $urlBuilder,
        LoggerInterface $logger
    ) {
        $this->localeResolver = $localeResolver;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
    }

    /**
     * @param Store $store
     * @param NostoAccount $account
     * @return NostoOauth
     */
    public function build(Store $store, NostoAccount $account = null)
    {
        $metaData = new NostoOAuth();

        try {
            $metaData->setScopes(\NostoApiToken::getApiTokenNames());
            $redirectUrl = $this->urlBuilder->getUrl(
                'nosto/oauth',
                [
                    '_nosid' => true,
                    '_scope_to_url' => true,
                    '_scope' => $store->getCode(),
                ]
            );
            $metaData->setRedirectUrl($redirectUrl);
            $lang = substr($this->localeResolver->getLocale(), 0, 2);
            $metaData->setLanguage($lang);
            if (!is_null($account)) {
                $metaData->setAccount($account);
            }
        } catch (\NostoException $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
