<?php

namespace Nosto\Tagging\Block;

use Magento\Catalog\Model\ResourceModel\Url;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Tagging\Helper\Format as FormatHelper;
use Nosto\Tagging\Model\Cart\Builder as CartBuilder;

require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Cart block used for outputting meta-data on the stores product pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Cart extends \Magento\Checkout\Block\Cart
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
     * Constructor.
     *
     * @param Context                             $context
     * @param Session                             $customerSession
     * @param \Magento\Checkout\Model\Session     $checkoutSession
     * @param Url                                 $catalogUrlBuilder
     * @param \Magento\Checkout\Helper\Cart       $cartHelper
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param CartBuilder                         $cartBuilder
     * @param FormatHelper                        $formatHelper
     * @param array                               $data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        Url $catalogUrlBuilder,
        \Magento\Checkout\Helper\Cart $cartHelper,
        \Magento\Framework\App\Http\Context $httpContext,
        CartBuilder $cartBuilder,
        FormatHelper $formatHelper,
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
    }

    /**
     * Returns the Nosto cart DTO.
     *
     * @return \NostoCart
     */
    public function getNostoCart()
    {
        return $this->_cartBuilder->build(
            $this->getItems(),
            $this->_storeManager->getStore()
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
