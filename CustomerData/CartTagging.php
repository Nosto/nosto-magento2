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
use Nosto\Sdk\NostoCartItemInterface;
use Nosto\Tagging\Model\Cart\Builder as NostoCartBuilder;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Nosto\Tagging\Model\CustomerFactory as NostoCustomerFactory;

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
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var NostoCustomerFactory
     */
    protected $nostoCustomerFactory;

    /**
     * @var \Magento\Quote\Model\Quote|null
     */
    protected $quote = null;

    /**
     * @param CartHelper $cartHelper
     * @param NostoCartBuilder $nostoCartBuilder
     * @param StoreManagerInterface $storeManager
     * @param CookieManagerInterface $cookieManager
     * @param NostoCustomerFactory $nostoCustomerFactory
     */
    public function __construct(
        CartHelper $cartHelper,
        NostoCartBuilder $nostoCartBuilder,
        StoreManagerInterface $storeManager,
        CookieManagerInterface $cookieManager,
        NostoCustomerFactory $nostoCustomerFactory
    ) {
        $this->cartHelper= $cartHelper;
        $this->nostoCartBuilder = $nostoCartBuilder;
        $this->storeManager = $storeManager;
        $this->cookieManager = $cookieManager;
        $this->nostoCustomerFactory = $nostoCustomerFactory;
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
        $items = $this->getQuote()->getAllVisibleItems();
        $nostoCart = $this->nostoCartBuilder->build(
            $items,
            $this->storeManager->getStore()
        );
        $itemCount = $cart->getItemsCount();
        $data["itemCount"] = $itemCount;
        $addedCount = 0;
        /* @var NostoCartItemInterface $item */
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

        if ($data["itemCount"] > 0) {
            $this->updateNostoId();
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
            $cart = $this->cartHelper->getCart();
            $this->quote = $cart->getQuote();
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

    private function updateNostoId() {
        // Handle the Nosto customer & quote mapping
        $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        $quoteId = $this->getQuote()->getId();
        if (!empty($quoteId) && !empty($nostoCustomerId)) {
            $nostoCustomer = $this->nostoCustomerFactory
                ->create()
                ->getCollection()
                ->addFieldToFilter(NostoCustomer::QUOTE_ID, $quoteId)
                ->addFieldToFilter(NostoCustomer::NOSTO_ID, $nostoCustomerId)
                ->setPageSize(1)
                ->setCurPage(1)
                ->getFirstItem();
            if ($nostoCustomer->hasData(NostoCustomer::CUSTOMER_ID)) {
                $nostoCustomer->setUpdatedAt(new \DateTime('now'));
            } else {
                $nostoCustomer = $this->nostoCustomerFactory->create();
                $nostoCustomer->setQuoteId($quoteId);
                $nostoCustomer->setNostoId($nostoCustomerId);
                $nostoCustomer->setCreatedAt(new \DateTime('now'));
                $nostoCustomer->setUpdatedAt(new \DateTime('now'));
            }
            try {
                $nostoCustomer->save();
            } catch (\Exception $e) {
                //Todo - handle errors, maybe log?
            }
        }
    }
}
