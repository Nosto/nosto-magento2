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

namespace Nosto\Tagging\Helper;

use Magento\Bundle\Model\Product\Price as BundlePrice;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedType;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Price helper used for product price related tasks.
 */
class Price extends AbstractHelper
{
    private $_catalogHelper;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param CatalogHelper $catalogHelper the catalog helper.
     */
    public function __construct(
        Context $context,
        CatalogHelper $catalogHelper
    )
    {
        parent::__construct($context);

        $this->_catalogHelper = $catalogHelper;
    }

    /**
     * Gets the unit price for a product model including taxes.
     *
     * @param Product $product the product model.
     *
     * @return float
     */
    public function getProductPriceInclTax($product)
    {
        return $this->_getProductPrice($product, false, true);
    }

    /**
     * Get the final price for a product model including taxes.
     *
     * @param Product $product the product model.
     *
     * @return float
     */
    public function getProductFinalPriceInclTax($product)
    {
        return $this->_getProductPrice($product, true, true);
    }

    /**
     * Get unit/final price for a product model.
     *
     * @param Product $product the product model.
     * @param bool $finalPrice if final price.
     * @param bool $inclTax if tax is to be included.
     *
     * @return float
     */
    protected function _getProductPrice($product, $finalPrice = false, $inclTax = true)
    {
        switch ($product->getTypeId()) {
            // Get the bundle product "from" price.
            case ProductType::TYPE_BUNDLE:
                /** @var BundlePrice $priceModel */
                $priceModel = $product->getPriceModel();
                // todo: from price discount?
                $price = $priceModel->getTotalPrices($product, 'min', $inclTax);
                break;

            // No constant for this value was found (Magento ver. 1.0.0-beta).
            // Get the grouped product "minimal" price.
            case 'grouped':
                /* @var $typeInstance GroupedType */
                $typeInstance = $product->getTypeInstance();
                $associatedProducts = $typeInstance
                    ->setStoreFilter($product->getStore(), $product)
                    ->getAssociatedProducts($product);
                $cheapestAssociatedProduct = null;
                $minimalPrice = 0;
                foreach ($associatedProducts as $associatedProduct) {
                    /** @var Product $associatedProduct */
                    $tmpPrice = $finalPrice
                        ? $associatedProduct->getFinalPrice()
                        : $associatedProduct->getPrice();
                    if ($minimalPrice === 0 || $minimalPrice > $tmpPrice) {
                        $minimalPrice = $tmpPrice;
                        $cheapestAssociatedProduct = $associatedProduct;
                    }
                }
                $price = $minimalPrice;
                if ($inclTax && $cheapestAssociatedProduct) {
                    $price = $this->_catalogHelper->getTaxPrice(
                        $cheapestAssociatedProduct,
                        $price,
                        true
                    );
                }
                break;

            // No constant for this value was found (Magento ver. 1.0.0-beta).
            // The configurable product has the tax already applied in the
            // "final" price, but not in the regular price.
            case 'configurable':
                if ($finalPrice) {
                    $price = $product->getFinalPrice();
                } elseif ($inclTax) {
                    $price = $this->_catalogHelper->getTaxPrice(
                        $product,
                        $product->getPrice(),
                        true
                    );
                } else {
                    $price = $product->getPrice();
                }
                break;

            default:
                $price = $finalPrice
                    ? $product->getFinalPrice()
                    : $product->getPrice();
                if ($inclTax) {
                    $price = $this->_catalogHelper->getTaxPrice(
                        $product,
                        $price,
                        true
                    );
                }
                break;
        }

        return $price;
    }
}
