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

use Magento\Checkout\Block\Success;
use Magento\Checkout\Model\Session;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\OrderFactory;
use Nosto\Tagging\Helper\Format;
use Nosto\Tagging\Model\Order\Builder as OrderBuilder;
use NostoPrice;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Category block used for outputting meta-data on the stores category pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Order extends Success
{
    /**
     * @inheritdoc
     */
    protected $_template = 'order.phtml';

    /**
     * @var OrderBuilder the order meta model builder.
     */
    protected $_orderBuilder;

    /**
     * @var Registry the framework registry.
     */
    protected $_registry;

    /**
     * @var Format the format helper.
     */
    protected $_formatHelper;
    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * Constructor.
     *
     * @param Template\Context $context
     * @param OrderFactory $orderFactory
     * @param OrderBuilder $orderBuilder
     * @param Format $formatHelper
     * @param Session $checkoutSession
     * @param array $data
     * @internal param Registry $registry
     * @internal param CategoryBuilder $categoryBuilder
     */
    public function __construct(
        Template\Context $context,
        OrderFactory $orderFactory,
        OrderBuilder $orderBuilder,
        Format $formatHelper,
        Session $checkoutSession,
        array $data = []
    )
    {
        parent::__construct(
            $context,
            $orderFactory,
            $data
        );

        $this->_formatHelper = $formatHelper;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderBuilder = $orderBuilder;
    }

    /**
     * Returns the Nosto order meta-data model.
     *
     * @return \NostoOrder the order meta data model.
     */
    public function getNostoOrder()
    {
        /** @var \Magento\Sales\Model\Order $order */
        return $this->_orderBuilder->build($this->_checkoutSession->getLastRealOrder());
    }

    /**
     * Formats a \NostoPrice object, e.g. "1234.56".
     *
     * @param NostoPrice $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice(NostoPrice $price)
    {
        return $this->_formatHelper->formatPrice($price);
    }

    /**
     * Formats a \NostoDate object, e.g. "2015-12-24";
     *
     * @param \NostoDate $date the date to format.
     * @return string the formatted date.
     */
    public function formatNostoDate(\NostoDate $date)
    {
        return $this->_formatHelper->formatDate($date);
    }
}
