<?php
/**
 * Created by PhpStorm.
 * User: hannupolonen
 * Date: 16/12/16
 * Time: 14:39
 */
namespace Nosto\Tagging\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Store\Api\StoreManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Model\Cart\Builder as NostoCartBuilder;


class CartTagging implements SectionSourceInterface
{

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    protected $cartHelper;

    /**
     * @var \Nosto\Tagging\Model\Cart\Builder
     */
    protected $nostoCartBuilder;

    /**
     * @var StoreManagementInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Quote\Model\Quote|null
     */
    protected $quote = null;

    /**
     * @param CartHelper $cartHelper
     * @param NostoCartBuilder $nostoCartBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        CartHelper $cartHelper,
        NostoCartBuilder $nostoCartBuilder,
        StoreManagerInterface $storeManager
    )
    {
        $this->cartHelper= $cartHelper;
        $this->nostoCartBuilder = $nostoCartBuilder;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function getSectionData()
    {
        $data = [
            "items" => [],
            "itemCount" => 0,
        ];
        $cart = $this->cartHelper->getCart();
        $items = $cart->getQuote()->getAllVisibleItems();
        $nostoCart = $this->nostoCartBuilder->build(
            $items,
            $this->storeManager->getStore()
        );
        $itemCount = $cart->getItemsCount();
        $data["itemCount"] = $itemCount;
        $addedCount = 0;
        /* @var \NostoCartItemInterface $item */
        foreach ($nostoCart->getItems() as $item) {
            $addedCount++;
            $data["items"][] = [
                'product_id' => $item->getItemId(),
                'quantity' => $item->getQuantity(),
                'name' => $item->getName(),
                'unit_price' => $item->getUnitPrice()->getPrice(),
                'price_currency_code' => $item->getCurrency()->getCode(),
                'total_count' => $itemCount,
                'index' => $addedCount
            ];
        }

        return $data;
    }

    /**
     * Get active quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        if (!$this->quote) {
            $session = $this->checkoutSession;
            $this->quote = $session->getQuote();
        }

        return $this->quote;
    }

    /**
     * Return customer quote items
     *
     * @return \Magento\Quote\Model\Quote\Item[]
     */
    protected function getAllQuoteItems()
    {

        $quote = $this->getQuote();
        return $quote->getAllVisibleItems();
    }
}
