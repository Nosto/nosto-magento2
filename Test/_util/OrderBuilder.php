<?php
namespace Nosto\Tagging\Test\_util;

use Magento\Catalog\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Order\Visibility;
use Magento\Catalog\Model\Order\Attribute\Source\Status;
use Magento\Sales\Model\Order\Payment;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Sales\Model\Order\Address;
use Magento\Checkout\Model\Session as CheckoutSession;

final class OrderBuilder implements BuilderInterface
{
    /* @var Order */
    private $order;

    /* @var ObjectManagerInterface */
    private $objectManager;

    /**
     * OrderBuilder constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
        $this->order = $objectManager->create(Order::class);
    }

    /**
     * @return $this
     * @magentoDataFixture createOrderFixture
     */
    public function defaultOrder()
    {
        $this->order = self::createOrderFixture(999);
        return $this;
    }

    /**
     * @return Order
     */
    public function build()
    {
        return $this->order;
    }

    /**
     * @return array
     */
    private static function getAddresData()
    {
        return [
            'region' => 'Uusimaa',
            'region_id' => '336',
            'postcode' => '00180',
            'lastname' => 'Solutions',
            'firstname' => 'Nosto',
            'street' => 'Bulevardi 21',
            'city' => 'Helsinki',
            'email' => 'devnull@nosto.com',
            'telephone' => '11111111',
            'country_id' => 'FI'
        ];
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public static function createOrderFixture($orderId)
    {
        /** @var Address $billingAddress */
        $billingAddress = Bootstrap::getObjectManager()->create(Address::class, ['data' => self::getAddresData()]);
        $billingAddress->setAddressType('billing');

        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)->setAddressType('shipping');

        /** @var Payment $payment */
        $payment = Bootstrap::getObjectManager()->create(Payment::class);
        $payment->setMethod('checkmo')
            ->setAdditionalInformation([
                'token_metadata' => [
                    'token'       => 'f34vjw',
                    'customer_id' => 1,
                ],
            ]);
        $order = Bootstrap::getObjectManager()->create(Order::class);
        $order->setId($orderId)
            ->setIncrementId('100000001')
            ->setState(Order::STATE_PENDING_PAYMENT)
            ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PENDING_PAYMENT))
            ->setSubtotal(100)
            ->setGrandTotal(100)
            ->setBaseSubtotal(100)
            ->setBaseGrandTotal(100)
            ->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@null.com')
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setStoreId(Bootstrap::getObjectManager()->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore()->getId())
            ->setPayment($payment)
            ->save();

        return $order;
    }

}
