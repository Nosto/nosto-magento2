<?php

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
     * @var Price\Variation\Factory
     */
    protected $_variationFactory;

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
     * @param Factory                     $productFactory
     * @param Price\Variation\Factory     $variationFactory
     * @param DataHelper                  $dataHelper
     * @param PriceHelper                 $priceHelper
     * @param CategoryBuilder             $categoryBuilder
     * @param CategoryRepositoryInterface $categoryRepository
     * @param LoggerInterface             $logger
     */
    public function __construct(
        Factory $productFactory,
        Price\Variation\Factory $variationFactory,
        DataHelper $dataHelper,
        PriceHelper $priceHelper,
        CategoryBuilder $categoryBuilder,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
    ) {
        $this->_productFactory = $productFactory;
        $this->_variationFactory = $variationFactory;
        $this->_dataHelper = $dataHelper;
        $this->_priceHelper = $priceHelper;
        $this->_categoryBuilder = $categoryBuilder;
        $this->_categoryRepository = $categoryRepository;
        $this->_logger = $logger;
    }

    /**
     * @param Product $product
     * @param Store   $store
     * @return \Nosto\Tagging\Model\Product
     */
    public function build(Product $product, Store $store)
    {
        $nostoProduct = $this->_productFactory->create();

        try {
            $nostoProduct->setUrl($this->buildUrl($product, $store));
            $nostoProduct->setProductId($product->getSku());
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

            if ($product->hasData('short_description')) {
                $nostoProduct->setShortDescription(
                    $product->getData('short_description')
                );
            }
            if ($product->hasData('description')) {
                $nostoProduct->setDescription($product->getData('description'));
            }
            if ($product->hasData('manufacturer')) {
                $nostoProduct->setBrand(
                    $product->getAttributeText('manufacturer')
                );
            }
            if (($tags = $this->buildTags($product, $store)) !== []) {
                $nostoProduct->setTag1($tags);
            }
            if ($product->hasData('created_at')) {
                if (($timestamp = strtotime($product->getData('created_at')))) {
                    $nostoProduct->setDatePublished(new \NostoDate($timestamp));
                }
            }

            $currencies = $store->getAvailableCurrencyCodes(true);
            if (count($currencies) > 1) {
                $nostoProduct->setPriceVariationId(
                    new \NostoPriceVariation($store->getBaseCurrencyCode())
                );
                if ($this->_dataHelper
                    ->isMultiCurrencyMethodPriceVariation($store)
                ) {
                    $nostoProduct->setPriceVariations(
                        $this->buildPriceVariations($product, $store)
                    );
                }
            }
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $nostoProduct;
    }

    /**
     * @param Product $product
     * @param Store   $store
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
     * @param Store   $store
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
     * @param Store   $store
     * @return array
     */
    protected function buildTags(Product $product, Store $store)
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
            $tags[] = \Nosto\Tagging\Model\Product::PRODUCT_ADD_TO_CART;
        }

        return $tags;
    }

    /**
     * @param Product $product
     * @param Store   $store
     * @return array
     */
    protected function buildPriceVariations(Product $product, Store $store)
    {
        $variations = array();
        $currencyCodes = $store->getAvailableCurrencyCodes(true);
        foreach ($currencyCodes as $currencyCode) {
            // Skip base currency.
            if ($currencyCode === $store->getBaseCurrencyCode()) {
                continue;
            }
            try {
                $id = new \NostoPriceVariation($currencyCode);
                $currency = new \NostoCurrencyCode($currencyCode);
                $price = $product->getFinalPrice();
                $price = $store->getBaseCurrency()->convert(
                    $price,
                    $currencyCode
                );
                $price = new \NostoPrice($price);
                $unitPrice = $product->getPrice();
                $unitPrice = $store->getBaseCurrency()->convert(
                    $unitPrice,
                    $currencyCode
                );
                $unitPrice = new \NostoPrice($unitPrice);
                $availability = new \NostoProductAvailability(
                    $product->isAvailable()
                        ? \NostoProductAvailability::IN_STOCK
                        : \NostoProductAvailability::OUT_OF_STOCK
                );
                $variations[] = $this->_variationFactory->create(
                    [
                        'id' => $id,
                        'currency' => $currency,
                        'price' => $price,
                        'unitPrice' => $unitPrice,
                        'availability' => $availability
                    ]
                );
            } catch (\Exception $e) {
                // The price variation cannot be obtained if there are no
                // exchange rates defined for the currency and Magento will
                // throw and exception. Just ignore this and continue.
                continue;
            }
        }
        return $variations;
    }
}
