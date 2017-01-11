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
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Category\Builder as NostoCategoryBuilder;
use Nosto\Tagging\Model\Product\Builder as NostoProductBuilder;
use NostoHelperDate;
use NostoHelperPrice;
use NostoProduct;

/**
 * Product block used for outputting meta-data on the stores product pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Product extends View
{
    /**
     * @var NostoProductBuilder the product meta model builder.
     */
    protected $nostoProductBuilder;

    /**
     * @var NostoCategoryBuilder the category meta model builder.
     */
    protected $categoryBuilder;

    /**
     * @var NostoHelperData the data helper.
     */
    protected $nostoHelperData;

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
     * @param NostoHelperData $nostoHelperData the data helper.
     * @param array $data optional data.
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
        NostoHelperData $nostoHelperData,
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

        $this->nostoProductBuilder = $nostoProductBuilder;
        $this->categoryBuilder = $categoryBuilder;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * Returns the Nosto product DTO.
     *
     * @return NostoProduct the product meta data model.
     */
    public function getNostoProduct()
    {
        /** @var Store $store */
        $store = $this->_storeManager->getStore();
        return $this->nostoProductBuilder->build(
            $this->getProduct(),
            $store
        );
    }

    /**
     * Returns the Nosto category DTO.
     *
     * @return string the current category as a slash-delimited string
     */
    public function getNostoCategory()
    {
        $category = $this->_coreRegistry->registry('current_category');
        return !is_null($category)
            ? $this->categoryBuilder->build($category)
            : null;
    }

    /**
     * Formats a price e.g. "1234.56".
     *
     * @param int $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice($price)
    {
        return NostoHelperPrice::format($price);
    }

    /**
     * Formats a date, e.g. "2015-12-24";
     *
     * @param string $date the date to format.
     * @return string the formatted date.
     */
    public function formatNostoDate($date)
    {
        return NostoHelperDate::format($date);
    }
}
