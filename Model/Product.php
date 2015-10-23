<?php

namespace Nosto\Tagging\Model;

class Product implements \NostoProductInterface
{
    /**
     * Product "can be directly added to cart" tag string.
     */
    const PRODUCT_ADD_TO_CART = 'add-to-cart';

    /**
     * @var string the absolute url to the product page in the shop frontend.
     */
    protected $_url;

    /**
     * @var int|string the product's unique identifier.
     */
    protected $_productId;

    /**
     * @var string the name of the product.
     */
    protected $_name;

    /**
     * @var string the absolute url the one of the product images in frontend.
     */
    protected $_imageUrl;

    /**
     * @var \NostoPrice the product price including possible discounts and taxes.
     */
    protected $_price;

    /**
     * @var \NostoPrice the product list price without discounts but incl taxes.
     */
    protected $_listPrice;

    /**
     * @var \NostoCurrencyCode the currency code the product is sold in.
     */
    protected $_currency;

    /**
     * @var \NostoPriceVariation the price variation currently in use.
     */
    protected $_priceVariation;

    /**
     * @var \NostoProductAvailability the availability of the product.
     */
    protected $_availability;

    /**
     * @var array the tags for the product.
     */
    protected $_tags = array(
        'tag1' => [],
        'tag2' => [],
        'tag3' => [],
    );

    /**
     * @var array the categories the product is located in.
     */
    protected $_categories = [];

    /**
     * @var string the product short description.
     */
    protected $_shortDescription;

    /**
     * @var string the product description.
     */
    protected $_description;

    /**
     * @var string the product brand name.
     */
    protected $_brand;

    /**
     * @var \NostoDate the product publication date in the shop.
     */
    protected $_datePublished;

    /**
     * @var \NostoProductPriceVariationInterface[] the product price variations.
     */
    protected $_priceVariations = [];

    /**
     * Returns the absolute url to the product page in the shop frontend.
     *
     * @return string the url.
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Returns the product's unique identifier.
     *
     * @return int|string the ID.
     */
    public function getProductId()
    {
        return $this->_productId;
    }

    /**
     * Returns the name of the product.
     *
     * @return string the name.
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Returns the absolute url the one of the product images in the frontend.
     *
     * @return string the url.
     */
    public function getImageUrl()
    {
        return $this->_imageUrl;
    }

    /**
     * Returns the absolute url to one of the product image thumbnails in the shop frontend.
     *
     * @return string the url.
     */
    public function getThumbUrl()
    {
        return null;
    }

    /**
     * Returns the price of the product including possible discounts and taxes.
     *
     * @return \NostoPrice the price.
     */
    public function getPrice()
    {
        return $this->_price;
    }

    /**
     * Returns the list price of the product without discounts but incl taxes.
     *
     * @return \NostoPrice the price.
     */
    public function getListPrice()
    {
        return $this->_listPrice;
    }

    /**
     * Returns the currency code (ISO 4217) the product is sold in.
     *
     * @return \NostoCurrencyCode the currency ISO code.
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * Returns the ID of the price variation that is currently in use.
     *
     * @return string the price variation ID.
     */
    public function getPriceVariationId()
    {
        return !is_null($this->_priceVariation)
            ? $this->_priceVariation->getId()
            : null;
    }

    /**
     * Returns the availability of the product, i.e. if it is in stock or not.
     *
     * @return \NostoProductAvailability the availability
     */
    public function getAvailability()
    {
        return $this->_availability;
    }

    /**
     * Returns the tags for the product.
     *
     * @return array the tags array, e.g. array('tag1' => array("winter", "shoe")).
     */
    public function getTags()
    {
        return $this->_tags;
    }

    /**
     * Returns the categories the product is located in.
     *
     * @return Category[] list of category objects.
     */
    public function getCategories()
    {
        return $this->_categories;
    }

    /**
     * Returns the product short description.
     *
     * @return string the short description.
     */
    public function getShortDescription()
    {
        return $this->_shortDescription;
    }

    /**
     * Returns the product description.
     *
     * @return string the description.
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Returns the product brand name.
     *
     * @return string the brand name.
     */
    public function getBrand()
    {
        return $this->_brand;
    }

    /**
     * Returns the product publication date in the shop.
     *
     * @return \NostoDate the date.
     */
    public function getDatePublished()
    {
        return $this->_datePublished;
    }

    /**
     * Returns the product price variations if any exist.
     *
     * @return \NostoProductPriceVariationInterface[] the price variations.
     */
    public function getPriceVariations()
    {
        return $this->_priceVariations;
    }

    /**
     * Returns the full product description,
     * i.e. both the "short" and "normal" descriptions concatenated.
     *
     * @return string the full descriptions.
     */
    public function getFullDescription()
    {
        $descriptions = array();
        if (!empty($this->_shortDescription)) {
            $descriptions[] = $this->_shortDescription;
        }
        if (!empty($this->_description)) {
            $descriptions[] = $this->_description;
        }
        return implode(' ', $descriptions);
    }

    /**
     * Sets the product ID from given product.
     *
     * The ID must be either an integer above zero, or a non-empty string.
     *
     * Usage:
     * $object->setProductId(1);
     *
     * @param int|string $id the product ID.
     *
     * @throws \InvalidArgumentException
     */
    public function setProductId($id)
    {
		if (!is_int($id) && !is_string($id)) {
			throw new \InvalidArgumentException('ID must be an integer or a string.');
		}
        if (is_int($id) && !($id > 0)) {
            throw new \InvalidArgumentException('ID must be an integer above zero.');
        }
		if (is_string($id) && empty($id)) {
			throw new \InvalidArgumentException('ID must be an non-empty string value.');
		}

        $this->_productId = $id;
    }

    /**
     * Sets the availability state of the product.
     *
     * The availability of the product must be either "InStock" or "OutOfStock",
     * represented as a value object of class `NostoProductAvailability`.
     *
     * Usage:
     * $object->setAvailability(new NostoProductAvailability(NostoProductAvailability::IN_STOCK));
     *
     * @param \NostoProductAvailability $availability the availability.
     */
    public function setAvailability(\NostoProductAvailability $availability)
    {
        $this->_availability = $availability;
    }

    /**
     * Sets the currency code (ISO 4217) the product is sold in.
     *
     * The currency must be in ISO 4217 format, represented as a value object of
     * class `NostoCurrencyCode`.
     *
     * Usage:
     * $object->setCurrency(new NostoCurrencyCode('USD'));
     *
     * @param \NostoCurrencyCode $currency the currency code.
     */
    public function setCurrency(\NostoCurrencyCode $currency)
    {
        $this->_currency = $currency;
    }

    /**
     * Sets the products published date.
     *
     * The date must be a UNIX timestamp, represented as a value object of
     * class `NostoDate`.
     *
     * Usage:
     * $object->setDatePublished(new NostoDate(strtotime('2015-01-01 00:00:00')));
     *
     * @param \NostoDate $date the date.
     */
    public function setDatePublished(\NostoDate $date)
    {
        $this->_datePublished = $date;
    }

    /**
     * Sets the product price.
     *
     * The price must be a numeric value, represented as a value object of
     * class `NostoPrice`.
     *
     * Usage:
     * $object->setPrice(new NostoPrice(99.99));
     *
     * @param \NostoPrice $price the price.
     */
    public function setPrice(\NostoPrice $price)
    {
        $this->_price = $price;
    }

    /**
     * Sets the product list price.
     *
     * The price must be a numeric value, represented as a value object of
     * class `NostoPrice`.
     *
     * Usage:
     * $object->setListPrice(new NostoPrice(99.99));
     *
     * @param \NostoPrice $listPrice the price.
     */
    public function setListPrice(\NostoPrice $listPrice)
    {
        $this->_listPrice = $listPrice;
    }

    /**
     * Sets the product price variation ID.
     *
     * The ID must be a non-empty string, represented as a value object of
     * class `NostoPriceVariation`.
     *
     * Usage:
     * $object->setPriceVariationId(new NostoPriceVariation('USD'));
     *
     * @param \NostoPriceVariation $priceVariation the price variation.
     */
    public function setPriceVariationId(\NostoPriceVariation $priceVariation)
    {
        $this->_priceVariation = $priceVariation;
    }

    /**
     * Sets the product price variations.
     *
     * The variations represent the possible product prices in different
     * currencies and must implement the `NostoProductPriceVariationInterface`
     * interface.
     * This is only used in multi currency environments when the multi currency
     * method is set to "priceVariations".
     *
     * Usage:
     * $object->setPriceVariations(array(NostoProductPriceVariationInterface $priceVariation [, ... ]))
     *
     * @param \NostoProductPriceVariationInterface[] $priceVariations the price variations.
     */
    public function setPriceVariations(array $priceVariations)
    {
        $this->_priceVariations = array();
        foreach ($priceVariations as $priceVariation) {
            $this->addPriceVariation($priceVariation);
        }
    }

    /**
     * Adds a product price variation.
     *
     * The variation represents the product price in another currency than the
     * base currency, and must implement the `NostoProductPriceVariationInterface`
     * interface.
     * This is only used in multi currency environments when the multi currency
     * method is set to "priceVariations".
     *
     * Usage:
     * $object->addPriceVariation(NostoProductPriceVariationInterface $priceVariation);
     *
     * @param \NostoProductPriceVariationInterface $priceVariation the price variation.
     */
    public function addPriceVariation(
        \NostoProductPriceVariationInterface $priceVariation
    ) {
        $this->_priceVariations[] = $priceVariation;
    }

    /**
     * Removes a product price variation at given index.
     *
     * Usage:
     * $object->removePriceVariationAt(0);
     *
     * @param int $index the index of the variation in the list.
     *
     * @throws \InvalidArgumentException
     */
    public function removePriceVariationAt($index)
    {
        if (!isset($this->_priceVariations[$index])) {
            throw new \InvalidArgumentException('No price variation found at given index.');
        }
        unset($this->_priceVariations[$index]);
    }

    /**
     * Sets all the tags to the `tag1` field.
     *
     * The tags must be an array of non-empty string values.
     *
     * Usage:
     * $object->setTag1(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     *
     * @throws \InvalidArgumentException
     */
    public function setTag1(array $tags)
    {
        $this->_tags['tag1'] = array();
        foreach ($tags as $tag) {
            $this->addTag1($tag);
        }
    }

    /**
     * Adds a new tag to the `tag1` field.
     *
     * The tag must be a non-empty string value.
     *
     * Usage:
     * $object->addTag1('customTag');
     *
     * @param string $tag the tag to add.
     *
     * @throws \InvalidArgumentException
     */
    public function addTag1($tag)
    {
        if (!is_string($tag) || empty($tag)) {
            throw new \InvalidArgumentException('Tag must be a non-empty string value.');
        }

        $this->_tags['tag1'][] = $tag;
    }

    /**
     * Sets all the tags to the `tag2` field.
     *
     * The tags must be an array of non-empty string values.
     *
     * Usage:
     * $object->setTag2(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     *
     * @throws \InvalidArgumentException
     */
    public function setTag2(array $tags)
    {
        $this->_tags['tag2'] = array();
        foreach ($tags as $tag) {
            $this->addTag2($tag);
        }
    }

    /**
     * Adds a new tag to the `tag2` field.
     *
     * The tag must be a non-empty string value.
     *
     * Usage:
     * $object->addTag2('customTag');
     *
     * @param string $tag the tag to add.
     *
     * @throws \InvalidArgumentException
     */
    public function addTag2($tag)
    {
        if (!is_string($tag) || empty($tag)) {
            throw new \InvalidArgumentException('Tag must be a non-empty string value.');
        }

        $this->_tags['tag2'][] = $tag;
    }

    /**
     * Sets all the tags to the `tag3` field.
     *
     * The tags must be an array of non-empty string values.
     *
     * Usage:
     * $object->setTag3(array('customTag1', 'customTag2'));
     *
     * @param array $tags the tags.
     *
     * @throws \InvalidArgumentException
     */
    public function setTag3(array $tags)
    {
        $this->_tags['tag3'] = array();
        foreach ($tags as $tag) {
            $this->addTag3($tag);
        }
    }

    /**
     * Adds a new tag to the `tag3` field.
     *
     * The tag must be a non-empty string value.
     *
     * Usage:
     * $object->addTag3('customTag');
     *
     * @param string $tag the tag to add.
     *
     * @throws \InvalidArgumentException
     */
    public function addTag3($tag)
    {
        if (!is_string($tag) || empty($tag)) {
            throw new \InvalidArgumentException('Tag must be a non-empty string value.');
        }

        $this->_tags['tag3'][] = $tag;
    }

    /**
     * Sets the brand name of the product manufacturer.
     *
     * The name must be a non-empty string.
     *
     * Usage:
     * $object->setBrand('Example');
     *
     * @param string $brand the brand name.
     *
     * @throws \InvalidArgumentException
     */
    public function setBrand($brand)
    {
        if (!is_string($brand) || empty($brand)) {
            throw new \InvalidArgumentException('Brand must be a non-empty string value.');
        }

        $this->_brand = $brand;
    }

    /**
     * Sets the product categories.
     *
     * The categories must be an array of \Nosto\Tagging\Model\Category objects.
     *
     * Usage:
     * $object->setCategories(array(\Nosto\Tagging\Model\Category [, ... ] ));
     *
     * @param Category[] $categories the categories.
     *
     * @throws \InvalidArgumentException
     */
    public function setCategories(array $categories)
    {
        $this->_categories = array();
        foreach ($categories as $category) {
            $this->addCategory($category);
        }
    }

    /**
     * Adds a category to the product.
     *
     * The category must be a non-empty string and is expected to include the
     * entire sub/parent category path, e.g. "clothes/winter/coats".
     *
     * Usage:
     * $object->addCategory(\Nosto\Tagging\Model\Category $category);
     *
     * @param Category $category the category.
     *
     * @throws \InvalidArgumentException
     */
    public function addCategory(Category $category)
    {
        if (!($category instanceof Category)) {
            throw new \InvalidArgumentException('Category must be an instance of \Nosto\Tagging\Model\Category.');
        }
        if (!$category->getPath()) {
            throw new \InvalidArgumentException('Category path must be a non-empty string value.');
        }

        $this->_categories[] = $category;
    }

    /**
     * Sets the product name.
     *
     * The name must be a non-empty string.
     *
     * Usage:
     * $object->setName('Example');
     *
     * @param string $name the name.
     *
     * @throws \InvalidArgumentException
     */
    public function setName($name)
    {
        if (!is_string($name) || empty($name)) {
            throw new \InvalidArgumentException('Name must be a non-empty string value.');
        }

        $this->_name = $name;
    }

    /**
     * Sets the URL for the product page in the shop that shows this product.
     *
     * The URL must be absolute, i.e. must include the protocol http or https.
     *
     * Usage:
     * $object->setUrl("http://my.shop.com/products/example.html");
     *
     * @param string $url the url.
     *
     * @throws \InvalidArgumentException
     */
    public function setUrl($url)
    {
        // todo
        /*if (!\Zend_Uri::check($url)) {
            throw new \InvalidArgumentException('URL must be valid and absolute.');
        }*/

        $this->_url = $url;
    }

    /**
     * Sets the image URL for the product.
     *
     * The URL must be absolute, i.e. must include the protocol http or https.
     *
     * Usage:
     * $object->setImageUrl("http://my.shop.com/media/example.jpg");
     *
     * @param string $imageUrl the url.
     *
     * @throws \InvalidArgumentException
     */
    public function setImageUrl($imageUrl)
    {
        // todo
        /*if (!\Zend_Uri::check($imageUrl)) {
            throw new \InvalidArgumentException('Image URL must be valid and absolute.');
        }*/

        $this->_imageUrl = $imageUrl;
    }

    /**
     * Sets the product description.
     *
     * The description must be a non-empty string.
     *
     * Usage:
     * $object->setDescription('Lorem ipsum dolor sit amet, ludus possim ut ius, bonorum facilis mandamus nam ea. ... ');
     *
     * @param string $description the description.
     *
     * @throws \InvalidArgumentException
     */
    public function setDescription($description)
    {
        if (!is_string($description) || empty($description)) {
            throw new \InvalidArgumentException('Description must be a non-empty string value.');
        }

        $this->_description = $description;
    }

    /**
     * Sets the product `short` description.
     *
     * The description must be a non-empty string.
     *
     * Usage:
     * $object->setShortDescription('Lorem ipsum dolor sit amet, ludus possim ut ius.');
     *
     * @param string $shortDescription the `short` description.
     *
     * @throws \InvalidArgumentException
     */
    public function setShortDescription($shortDescription)
    {
        if (!is_string($shortDescription) || empty($shortDescription)) {
            throw new \InvalidArgumentException('Short description must be a non-empty string value.');
        }

        $this->_shortDescription = $shortDescription;
    }
}
