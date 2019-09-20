<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Currency as CurrencyHelper;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Variation as NostoVariationHelper;

class ProductVariantProvider implements ProductProvider
{
    /**
     * @var ProductRatesVariantProvider
     */
    private $productRatesVariantProvider;
    /**
     * @var ProductMultiVariantProvider
     */
    private $productMultiVariantProvider;
    /**
     * @var NostoHelperData
     */
    private $nostoDataHelper;
    /**
     * @var NostoVariationHelper
     */
    private $nostoVariationHelper;
    /**
     * @var CurrencyHelper
     */
    private $nostoCurrencyHelper;

    public function __construct(
        NostoHelperData $nostoDataHelper,
        ProductRatesVariantProvider $productRatesVariantProvider,
        ProductMultiVariantProvider $productMultiVariantProvider,
        NostoVariationHelper $nostoVariationHelper,
        CurrencyHelper $nostoCurrencyHelper
    ) {
        $this->productRatesVariantProvider = $productRatesVariantProvider;
        $this->productMultiVariantProvider = $productMultiVariantProvider;
        $this->nostoDataHelper = $nostoDataHelper;
        $this->nostoVariationHelper = $nostoVariationHelper;
        $this->nostoCurrencyHelper = $nostoCurrencyHelper;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        // When using customer group price variations, set the variations
        if ($this->nostoDataHelper->isPricingVariationEnabled($store)
            && $this->nostoDataHelper->isMultiCurrencyDisabled($store)
        ) {
            $this->productMultiVariantProvider->addData($product, $store);
        }

        if ($this->nostoCurrencyHelper->exchangeRatesInUse($store)) {
            $nostoProduct->setVariationId($this->nostoCurrencyHelper->getTaggingCurrency($store)->getCode());
        } elseif ($this->nostoDataHelper->isPricingVariationEnabled($store)) {
            $nostoProduct->setVariationId(
                $this->nostoVariationHelper->getDefaultVariationCode()
            );
        }
    }
}