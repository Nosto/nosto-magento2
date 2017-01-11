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

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
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
                'hcid' => NostoHelperData::generateVisitorChecksum($nostoCustomerId),
            ];
        }

        return $data;
    }
}
