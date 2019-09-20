<?php


namespace Nosto\Tagging\Model\Product;


use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Catalog\Model\Product\Gallery\ReadHandler as GalleryReadHandler;
use Magento\Store\Model\Store;
use Nosto\Object\Product\Product as NostoProduct;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Util\Url as UrlUtil;

class ProductImagesProvider implements ProductProvider
{
    /**
     * @var GalleryReadHandler
     */
    private $galleryReadHandler;
    /**
     * @var NostoHelperData
     */
    private $nostoDataHelper;

    public function __construct(
        NostoHelperData $nostoDataHelper,
        GalleryReadHandler $galleryReadHandler
    ) {
        $this->galleryReadHandler = $galleryReadHandler;
        $this->nostoDataHelper = $nostoDataHelper;
    }

    function addData(MagentoProduct $product, Store $store, NostoProduct $nostoProduct)
    {
        if ($this->nostoDataHelper->isAltimgTaggingEnabled($store)) {
            $nostoProduct->setAlternateImageUrls($this->buildAlternativeImages($product));
        }
    }

    /**
     * Adds the alternative image urls
     *
     * @param MagentoProduct $product the product model.
     * @return array
     */
    public function buildAlternativeImages(MagentoProduct $product)
    {
        $images = [];
        $this->galleryReadHandler->execute($product);
        foreach ($product->getMediaGalleryImages() as $image) {
            if (isset($image['url']) && (isset($image['disabled']) && $image['disabled'] !== '1')) {
                $images[] = $this->finalizeImageUrl($image['url']);
            }
        }

        return $images;
    }

    /**
     * Finalizes product image urls, stips off "pub/" directory if applicable
     *
     * @param string $url
     * @return string
     */
    public function finalizeImageUrl($url)
    {
        return UrlUtil::removePubFromUrl($url);
    }
}