<?php
/**
 * Created by PhpStorm.
 * User: hannupolonen
 * Date: 16/12/16
 * Time: 14:39
 */
namespace Nosto\Tagging\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Tagging\Helper\Data as NostoDataHelper;
use Nosto\Tagging\Model\Customer as NostoCustomer;

class CustomerTagging implements SectionSourceInterface
{
    /*
     * @var CurrentCustomer
     */
    protected $currentCustomer;

    /*
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * Constructor
     *
     * @param CurrentCustomer $currentCustomer
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        CurrentCustomer $currentCustomer,
        CookieManagerInterface $cookieManager
    ) {
        $this->currentCustomer = $currentCustomer;
        $this->cookieManager = $cookieManager;
    }

    /**
     * @inheritdoc
     */
    public function getSectionData()
    {

        $data = [];
        if (
            $this->currentCustomer instanceof CurrentCustomer
            && $this->currentCustomer->getCustomerId()
        ) {
            $customer = $this->currentCustomer->getCustomer();
            $nostoCustomerId = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
            $data = [
                'first_name' => $customer->getFirstname(),
                'last_name' => $customer->getLastname(),
                'email' => $customer->getEmail(),
                'hcid' => NostoDataHelper::generateVisitorChecksum($nostoCustomerId),
            ];
        }

        return $data;
    }
}
