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
use Nosto\Sdk\NostoCategory;
use Nosto\Sdk\NostoDate;
use Nosto\Sdk\NostoPrice;
use Nosto\Sdk\NostoProduct;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Helper\Format;
use Nosto\Tagging\Model\Category\Builder as CategoryBuilder;
use Nosto\Tagging\Model\Product\Builder as ProductBuilder;

/**
 * Product block used for outputting meta-data on the stores product pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Product extends View
{
    /**
     * @inheritdoc
     */
    protected $_template = 'product.phtml';

    /**
     * @var ProductBuilder the product meta model builder.
     */
    protected $_productBuilder;

    /**
     * @var CategoryBuilder the category meta model builder.
     */
    protected $_categoryBuilder;

    /**
     * @var Data the data helper.
     */
    protected $_dataHelper;

    /**
     * @var Format the format helper.
     */
    protected $_formatHelper;

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
     * @param ProductBuilder $productBuilder the product meta model builder.
     * @param CategoryBuilder $categoryBuilder the category meta model builder.
     * @param Data $dataHelper the data helper.
     * @param Format $formatHelper the format helper.
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
        ProductBuilder $productBuilder,
        CategoryBuilder $categoryBuilder,
        Data $dataHelper,
        Format $formatHelper,
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

        $this->_productBuilder = $productBuilder;
        $this->_categoryBuilder = $categoryBuilder;
        $this->_dataHelper = $dataHelper;
        $this->_formatHelper = $formatHelper;
    }

    /**
     * Returns the Nosto product DTO.
     *
     * @return NostoProduct the product meta data model.
     */
    public function getNostoProduct()
    {
        /** @var Store $store */
        $store =  $this->_storeManager->getStore();
        return $this->_productBuilder->build(
            $this->getProduct(),
            $store
        );
    }

    /**
     * Returns the Nosto category DTO.
     *
     * @return NostoCategory the category meta data model.
     */
    public function getNostoCategory()
    {
        $category = $this->_coreRegistry->registry('current_category');
        return !is_null($category)
            ? $this->_categoryBuilder->build($category)
            : null;
    }

    /**
     * Formats a \NostoPrice object, e.g. "1234.56".
     *
     * @param NostoPrice $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice(NostoPrice $price)
    {
        return $this->_formatHelper->formatPrice($price);
    }

    /**
     * Formats a \NostoDate object, e.g. "2015-12-24";
     *
     * @param NostoDate $date the date to format.
     * @return string the formatted date.
     */
    public function formatNostoDate(NostoDate $date)
    {
        return $this->_formatHelper->formatDate($date);
    }
}
