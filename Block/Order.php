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
use /** @noinspection PhpUndefinedClassInspection */
    Magento\Sales\Model\OrderFactory;
use Nosto\Tagging\Helper\Format as NostoHelperFormat;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;

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
    protected $template = 'order.phtml';

    /**
     * @var NostoOrderBuilder the order meta model builder.
     */
    protected $nostoOrderBuilder;

    /**
     * @var Registry the framework registry.
     */
    protected $registry;

    /**
     * @var NostoHelperFormat the format helper.
     */
    protected $nostoFormatHelper;
    /**
     * @var Session
     */
    protected $checkoutSession;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param Template\Context $context
     * @param OrderFactory $orderFactory
     * @param NostoOrderBuilder $orderBuilder
     * @param NostoHelperFormat $formatHelper
     * @param Session $checkoutSession
     * @param array $data
     * @internal param Registry $registry
     * @internal param CategoryBuilder $categoryBuilder
     */
    public function __construct(
        Template\Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        OrderFactory $orderFactory,
        NostoOrderBuilder $orderBuilder,
        NostoHelperFormat $formatHelper,
        Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $orderFactory,
            $data
        );

        $this->nostoFormatHelper = $formatHelper;
        $this->checkoutSession = $checkoutSession;
        $this->nostoOrderBuilder = $orderBuilder;
    }

    /**
     * Returns the Nosto order meta-data model.
     *
     * @return \NostoOrder the order meta data model.
     */
    public function getNostoOrder()
    {
        /** @var \Magento\Sales\Model\Order $order */
        return $this->nostoOrderBuilder->build($this->checkoutSession->getLastRealOrder());
    }

    /**
     * NostoHelperFormats a \NostoPrice object, e.g. "1234.56".
     *
     * @param int $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice($price)
    {
        return $this->nostoFormatHelper->formatPrice($price);
    }

    /**
     * NostoHelperFormats a \NostoDate object, e.g. "2015-12-24";
     *
     * @param string $date the date to format.
     * @return string the formatted date.
     */
    public function formatNostoDate($date)
    {
        return $this->nostoFormatHelper->formatDate($date);
    }
}
