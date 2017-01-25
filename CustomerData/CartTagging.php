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
namespace Nosto\Tagging\CustomerData;

use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Api\StoreManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Model\Cart\Builder as NostoCartBuilder;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Nosto\Tagging\Model\CustomerFactory as NostoCustomerFactory;
use NostoLineItem;
use Psr\Log\LoggerInterface;

class CartTagging implements SectionSourceInterface
{

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    protected $cartHelper;

    /**
     * @var NostoCartBuilder
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

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @var NostoCustomerFactory
     */
    protected $nostoCustomerFactory;

    /**
     * @var \Magento\Quote\Model\Quote|null
     */
    protected $quote = null;

    /**
     * @var LoggerInterface
     */
    protected $logger;


    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @param CartHelper $cartHelper
     * @param NostoCartBuilder $nostoCartBuilder
     * @param StoreManagerInterface $storeManager
     * @param CookieManagerInterface $cookieManager
     * @param LoggerInterface $logger
     * @param NostoCustomerFactory $nostoCustomerFactory
     */
    public function __construct(
        CartHelper $cartHelper,
        NostoCartBuilder $nostoCartBuilder,
        StoreManagerInterface $storeManager,
        CookieManagerInterface $cookieManager,
        LoggerInterface $logger,
        /** @noinspection PhpUndefinedClassInspection */
        NostoCustomerFactory $nostoCustomerFactory
    ) {
        $this->cartHelper = $cartHelper;
        $this->nostoCartBuilder = $nostoCartBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
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
        $nostoCart = $this->nostoCartBuilder->build(
            $this->getQuote(),
            $this->storeManager->getStore()
        );
        $itemCount = $cart->getItemsCount();
        $data["itemCount"] = $itemCount;
        $addedCount = 0;
        /* @var NostoLineItem $item */
        foreach ($nostoCart->getItems() as $item) {
            $addedCount++;
            $data["items"][] = [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'name' => $item->getName(),
                'unit_price' => $item->getUnitPrice(),
                'price_currency_code' => $item->getPriceCurrencyCode(),
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

    private function updateNostoId()
    {
        // Handle the Nosto customer & quote mapping
        $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        $quoteId = $this->getQuote()->getId();
        if (!empty($quoteId) && !empty($nostoCustomerId)) {
            /** @noinspection PhpUndefinedMethodInspection */
            $customerQuery = $this->nostoCustomerFactory
                ->create()
                ->getCollection()
                ->addFieldToFilter(NostoCustomer::QUOTE_ID, $quoteId)
                ->addFieldToFilter(NostoCustomer::NOSTO_ID, $nostoCustomerId)
                ->setPageSize(1)
                ->setCurPage(1);

            /** @noinspection PhpUndefinedMethodInspection */
            $nostoCustomer = $customerQuery->getFirstItem(); // @codingStandardsIgnoreLine
            /** @noinspection PhpUndefinedMethodInspection */
            if ($nostoCustomer->hasData(NostoCustomer::CUSTOMER_ID)) {
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setUpdatedAt(new \DateTime('now'));
            } else {
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer = $this->nostoCustomerFactory->create();
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setQuoteId($quoteId);
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setNostoId($nostoCustomerId);
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setCreatedAt(new \DateTime('now'));
                /** @noinspection PhpUndefinedMethodInspection */
                $nostoCustomer->setUpdatedAt(new \DateTime('now'));
            }
            try {
                $nostoCustomer->save();
            } catch (\Exception $e) {
                $this->logger->error($e, ['exception' => $e]);
            }
        }
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
