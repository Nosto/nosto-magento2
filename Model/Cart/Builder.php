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

namespace Nosto\Tagging\Model\Cart;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Nosto\Tagging\Model\Cart\Item\Builder as NostoCartItemBuilder;
use NostoCart;
use Psr\Log\LoggerInterface;

class Builder
{
    /**
     * @var NostoCartItemBuilder
     */
    protected $nostoCartItemBuilder;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;
    /**
     * Event manager
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * Constructor.
     *
     * @param NostoCartItemBuilder $nostoCartItemBuilder
     * @param LoggerInterface $logger
     * @param ObjectManagerInterface $objectManager
     * @param ManagerInterface $eventManager
     * @internal param CartItemFactory $cartItemFactory
     */
    public function __construct(
        NostoCartItemBuilder $nostoCartItemBuilder,
        LoggerInterface $logger,
        ObjectManagerInterface $objectManager,
        ManagerInterface $eventManager
    ) {
        $this->objectManager = $objectManager;
        $this->nostoCartItemBuilder = $nostoCartItemBuilder;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @param Quote $quote
     * @param Store $store
     * @return NostoCart
     * @internal param array $items
     */
    public function build(Quote $quote, Store $store)
    {
        /** @var NostoCart $nostoCart */
        $nostoCart = $this->objectManager->create('NostoCart', null);

        foreach ($quote->getAllVisibleItems() as $item) {
            try {
                $cartItem = $this->nostoCartItemBuilder->build($item, $store);
                $nostoCart->addItem($cartItem);
            } catch (\NostoException $e) {
                $this->logger->error($e, ['exception' => $e]);
            }
        }

        $this->eventManager->dispatch(
            'nosto_cart_load_after',
            ['cart' => $nostoCart]
        );

        return $nostoCart;
    }
}
