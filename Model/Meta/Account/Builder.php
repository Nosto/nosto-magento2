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

namespace Nosto\Tagging\Model\Meta\Account;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Meta\Account\Billing\Builder as NostoBillingBuilder;
use Nosto\Tagging\Model\Meta\Account\Owner\Builder as NostoOwnerBuilder;
use NostoHttpRequest;
use NostoSignup;
use Psr\Log\LoggerInterface;

class Builder
{
    const API_TOKEN = 'YBDKYwSqTCzSsU8Bwbg4im2pkHMcgTy9cCX7vevjJwON1UISJIwXOLMM0a8nZY7h';
    const PLATFORM_NAME = 'Magento';
    private $nostoHelperData;
    private $accountOwnerMetaBuilder;
    private $accountBillingMetaBuilder;
    private $localeResolver;
    private $logger;

    /**
     * @param NostoHelperData $nostoHelperData
     * @param NostoOwnerBuilder $nostoAccountOwnerMetaBuilder
     * @param NostoBillingBuilder $nostoAccountBillingMetaBuilder
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        NostoHelperData $nostoHelperData,
        NostoOwnerBuilder $nostoAccountOwnerMetaBuilder,
        NostoBillingBuilder $nostoAccountBillingMetaBuilder,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->accountOwnerMetaBuilder = $nostoAccountOwnerMetaBuilder;
        $this->accountBillingMetaBuilder = $nostoAccountBillingMetaBuilder;
        $this->localeResolver = $localeResolver;
        $this->logger = $logger;
    }

    /**
     * @param Store $store
     * @return NostoSignup
     */
    public function build(Store $store)
    {
        $metaData = new NostoSignup(Builder::PLATFORM_NAME, Builder::API_TOKEN, null);

        try {
            $metaData->setTitle(
                implode(
                    ' - ',
                    [
                        $store->getWebsite()->getName(),
                        $store->getGroup()->getName(),
                        $store->getName()
                    ]
                )
            );
            $metaData->setName(substr(sha1(rand()), 0, 8));
            $metaData->setFrontPageUrl(
                NostoHttpRequest::replaceQueryParamInUrl(
                    '___store',
                    $store->getCode(),
                    $store->getBaseUrl(UrlInterface::URL_TYPE_WEB)
                )
            );

            $metaData->setCurrencyCode($store->getBaseCurrencyCode());
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setLanguageCode($lang);
            $lang = substr($this->localeResolver->getLocale(), 0, 2);
            $metaData->setOwnerLanguageCode($lang);

            $owner = $this->accountOwnerMetaBuilder->build();
            $metaData->setOwner($owner);

            $billing = $this->accountBillingMetaBuilder->build($store);
            $metaData->setBillingDetails($billing);
        } catch (\NostoException $e) {
            $this->logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
