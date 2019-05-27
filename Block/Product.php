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

/** @noinspection PhpDeprecationInspection */
namespace Nosto\Tagging\Block;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\View;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Json\EncoderInterface as JsonEncoder;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface as UrlEncoder;
use Nosto\Helper\DateHelper;
use Nosto\Helper\PriceHelper;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;

/**
 * Product block used for outputting meta-data on the stores product pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Product extends View
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

    private $nostoProductBuilder;
    private $categoryBuilder;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param UrlEncoder $urlEncoder the  url encoder.
     * @param JsonEncoder $jsonEncoder the json encoder.
     * @param StringUtils $string the string lib.
     * @param \Magento\Catalog\Helper\Product $productHelper the product helper.
     * @param ConfigInterface $productTypeConfig the product type config.
     * @param FormatInterface $localeFormat the locale format.
     * @param Session $customerSession the user session.
     * @param ProductRepositoryInterface $productRepository th product repository.
     * @param PriceCurrencyInterface $priceCurrency the price currency.
     * @param NostoProductBuilder $nostoProductBuilder the product meta model builder.
     * @param NostoCategoryBuilder $categoryBuilder the category meta model builder.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param array $data optional data.
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        UrlEncoder $urlEncoder,
        JsonEncoder $jsonEncoder,
        StringUtils $string,
        \Magento\Catalog\Helper\Product $productHelper,
        ConfigInterface $productTypeConfig,
        FormatInterface $localeFormat,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        NostoProductBuilder $nostoProductBuilder,
        NostoCategoryBuilder $categoryBuilder,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );

        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->categoryBuilder = $categoryBuilder;
    }

    /**
     * Returns the Nosto product DTO.
     *
     * @return \Nosto\Object\Product\Product the product meta data model.
     * @throws \Exception
     */
    public function getAbstractObject()
    {
        $store = $this->nostoHelperScope->getStore();
        return $this->nostoProductBuilder->build(
            $this->getProduct(),
            $store,
            NostoProductBuilder::NOSTO_SCOPE_TAGGING
        );
    }

    /**
     * Returns the Nosto category DTO.
     *
     * @return string|null the current category as a slash-delimited string
     */
    public function getNostoCategory()
    {
        $category = $this->_coreRegistry->registry('current_category');
        $store = $this->nostoHelperScope->getStore();
        return $category !== null ? $this->categoryBuilder->build($category, $store) : null;
    }

    /**
     * Formats a price e.g. "1234.56".
     *
     * @param int $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice($price)
    {
        return PriceHelper::format($price);
    }

    /**
     * Formats a date, e.g. "2015-12-24";
     *
     * @param string $date the date to format.
     * @return string the formatted date.
     */
    public function formatNostoDate($date)
    {
        return DateHelper::format($date);
    }

    /**
     * Checks if store uses more than one currency in order to decide whether to hide or show the
     * nosto_variation tagging.
     *
     * @return bool a boolean value indicating whether the store has more than one currency
     */
    public function hasMultipleCurrencies()
    {
        $store = $this->nostoHelperScope->getStore(true);
        return count($store->getAvailableCurrencyCodes(true)) > 1;
    }
}
