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
use Nosto\Sdk\NostoCurrencyCode;
use Nosto\Sdk\NostoHttpRequest;
use Nosto\Sdk\NostoLanguageCode;
use Nosto\Tagging\Helper\Currency;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Model\Meta\Account\Billing\Builder as BillingBuilder;
use Nosto\Tagging\Model\Meta\Account\Owner\Builder as OwnerBuilder;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @param Data $dataHelper
     * @param Currency $currencyHelper
     * @param OwnerBuilder $accountOwnerMetaBuilder
     * @param BillingBuilder $accountBillingMetaBuilder
     * @param ResolverInterface $localeResolver
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $dataHelper,
        Currency $currencyHelper,
        OwnerBuilder $accountOwnerMetaBuilder,
        BillingBuilder $accountBillingMetaBuilder,
        ResolverInterface $localeResolver,
        LoggerInterface $logger
    ) {
        $this->_dataHelper = $dataHelper;
        $this->_currencyHelper = $currencyHelper;
        $this->_accountOwnerMetaBuilder = $accountOwnerMetaBuilder;
        $this->_accountBillingMetaBuilder = $accountBillingMetaBuilder;
        $this->_localeResolver = $localeResolver;
        $this->_logger = $logger;
    }

    /**
     * @param Store $store
     * @return \Nosto\Sdk\NostoAccount
     */
    public function build(Store $store)
    {
        $metaData = new \Nosto\Sdk\NostoAccount();

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

            $metaData->setCurrency(
                new NostoCurrencyCode($store->getBaseCurrencyCode())
            );
            $lang = substr($store->getConfig('general/locale/code'), 0, 2);
            $metaData->setLanguage(new NostoLanguageCode($lang));
            $lang = substr($this->_localeResolver->getLocale(), 0, 2);
            $metaData->setOwnerLanguage(new NostoLanguageCode($lang));

            $owner = $this->_accountOwnerMetaBuilder->build();
            $metaData->setOwner($owner);

            $billing = $this->_accountBillingMetaBuilder->build($store);
            $metaData->setBilling($billing);
        } catch (\Nosto\Sdk\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
