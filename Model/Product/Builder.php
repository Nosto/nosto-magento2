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

namespace Nosto\Tagging\Model\Product;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as DataHelper;
use Nosto\Tagging\Helper\Price as PriceHelper;
use Nosto\Tagging\Model\Category\Builder as CategoryBuilder;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @var Factory
     */
    protected $_productFactory;

    /**
     * @var DataHelper
     */
    protected $_dataHelper;

    /**
     * @var PriceHelper
     */
    protected $_priceHelper;

    /**
     * @var CategoryBuilder
     */
    protected $_categoryBuilder;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $_categoryRepository;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @param Factory $productFactory
     * @param DataHelper $dataHelper
     * @param PriceHelper $priceHelper
     * @param CategoryBuilder $categoryBuilder
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Factory $productFactory,
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        CategoryBuilder $categoryBuilder,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
    )
    {
        $this->_productFactory = $productFactory;
        $this->_dataHelper = $dataHelper;
        $this->_priceHelper = $priceHelper;
        $this->_categoryBuilder = $categoryBuilder;
        $this->_categoryRepository = $categoryRepository;
        $this->_logger = $logger;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return \NostoProduct
     */
    public function build(Product $product, Store $store)
    {
        $nostoProduct = $this->_productFactory->create();

        try {
            $nostoProduct->setUrl($this->buildUrl($product, $store));
            $nostoProduct->setProductId($product->getId());
            $nostoProduct->setName($product->getName());
            $nostoProduct->setImageUrl($this->buildImageUrl($product, $store));
            $price = $this->_priceHelper->getProductFinalPriceInclTax($product);
            $nostoProduct->setPrice(new \NostoPrice($price));
            $listPrice = $this->_priceHelper->getProductPriceInclTax($product);
            $nostoProduct->setListPrice(new \NostoPrice($listPrice));
            $nostoProduct->setCurrency(
                new \NostoCurrencyCode($store->getBaseCurrencyCode())
            );
            $nostoProduct->setAvailability(
                new \NostoProductAvailability(
                    $product->isAvailable()
                        ? \NostoProductAvailability::IN_STOCK
                        : \NostoProductAvailability::OUT_OF_STOCK
                )
            );
            $nostoProduct->setCategories($this->buildCategories($product));

            // Optional properties.

            $descriptions = array();
            if ($product->hasData('short_description')) {
                $descriptions[] = $product->getData('short_description');
            }
            if ($product->hasData('description')) {
                $descriptions[] = $product->getData('description');
            }
            if (count($descriptions) > 0) {
                $nostoProduct->setDescription(implode(' ', $descriptions));
            }

            if ($product->hasData('manufacturer')) {
                $nostoProduct->setBrand(
                    $product->getAttributeText('manufacturer')
                );
            }
            if (($tags = $this->buildTags($product)) !== []) {
                $nostoProduct->setTag1($tags);
            }
            if ($product->hasData('created_at')) {
                if (($timestamp = strtotime($product->getData('created_at')))) {
                    $nostoProduct->setDatePublished(new \NostoDate($timestamp));
                }
            }
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $nostoProduct;
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return string
     */
    protected function buildUrl(Product $product, Store $store)
    {
        return $product->getUrlInStore(
            [
                '_ignore_category' => true,
                '_nosid' => true,
                '_scope_to_url' => true,
                '_scope' => $store->getCode(),
            ]
        );
    }

    /**
     * @param Product $product
     * @param Store $store
     * @return string|null
     */
    protected function buildImageUrl(Product $product, Store $store)
    {
        $primary = $this->_dataHelper->getProductImageVersion($store);
        $secondary = 'image'; // The "base" image.
        $media = $product->getMediaAttributeValues();
        $image = (isset($media[$primary])
            ? $media[$primary]
            : (isset($media[$secondary]) ? $media[$secondary] : null)
        );

        if (empty($image)) {
            return null;
        }

        return $product->getMediaConfig()->getMediaUrl($image);
    }

    /**
     * @param Product $product
     * @return array
     */
    protected function buildCategories(Product $product)
    {
        $categories = [];
        foreach ($product->getCategoryCollection() as $category) {
            $categories[] = $this->_categoryBuilder->build($category);
        }
        return $categories;
    }

    /**
     * @param Product $product
     * @return array
     */
    protected function buildTags(Product $product)
    {
        $tags = [];

        foreach ($product->getAttributes() as $attr) {
            if ($attr->getIsVisibleOnFront()
                && $product->hasData($attr->getAttributeCode())
            ) {
                $label = $attr->getStoreLabel();
                $value = $attr->getFrontend()->getValue($product);
                if (is_string($label) && strlen($label)
                    && is_string($value) && strlen($value)
                ) {
                    $tags[] = "{$label}: {$value}";
                }
            }
        }

        if (!$product->canConfigure()) {
            $tags[] = \NostoProduct::PRODUCT_ADD_TO_CART;
        }

        return $tags;
    }
}
