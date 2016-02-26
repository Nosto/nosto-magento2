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

use Magento\Catalog\Model\ResourceModel\Url;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Format as FormatHelper;
use Nosto\Tagging\Model\Cart\Builder as CartBuilder;
use Nosto\Tagging\Model\CustomerFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Framework\App\Http\Context as HttpContext;
use Nosto\Tagging\Model\Customer as NostoCustomer;
use Magento\Checkout\Block\Cart as CartBlock;

/**
 * Cart block used for outputting meta-data on the stores product pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Cart extends CartBlock
{
    /**
     * @inheritdoc
     */
    protected $_template = 'cart.phtml';

    /**
     * @var FormatHelper the nosto format helper.
     */
    protected $_formatHelper;

    /**
     * @var CartBuilder the nosto cart builder.
     */
    protected $_cartBuilder;

    /**
     * @var CustomerFactory customer factory.
     */
    protected $_customerFactory;

    /**
     * @var CookieManager cookie manager.
     */
    protected $_cookieManager;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Url $catalogUrlBuilder
     * @param \Magento\Checkout\Helper\Cart $cartHelper
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param CartBuilder $cartBuilder
     * @param FormatHelper $formatHelper
     * @param CustomerFactory $customerFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        CookieManagerInterface $cookieManager,
        CheckoutSession $checkoutSession,
        Url $catalogUrlBuilder,
        CartHelper $cartHelper,
        HttpContext $httpContext,
        CartBuilder $cartBuilder,
        FormatHelper $formatHelper,
        CustomerFactory $customerFactory,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $catalogUrlBuilder,
            $cartHelper,
            $httpContext,
            $data
        );

        $this->_cartBuilder = $cartBuilder;
        $this->_formatHelper = $formatHelper;
        $this->_customerFactory = $customerFactory;
        $this->_cookieManager = $cookieManager;

        // Handle the Nosto customer & quote mapping
        $nostoCustomerId = $this->_cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        $quoteId = $this->getQuote()->getId();
        if (!empty($quoteId) && !empty($nostoCustomerId)) {
            $nostoCustomer = $this->_customerFactory
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
                $nostoCustomer = $this->_customerFactory->create();
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

    /**
     * Returns the Nosto cart DTO.
     *
     * @return \NostoCart
     */
    public function getNostoCart()
    {
        /** @var Store $store */
        $store =  $this->_storeManager->getStore();
        return $this->_cartBuilder->build(
            $this->getItems(),
            $store
        );
    }

    /**
     * Formats a \NostoPrice object, e.g. "1234.56".
     *
     * @param \NostoPrice $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice(\NostoPrice $price)
    {
        return $this->_formatHelper->formatPrice($price);
    }
}
