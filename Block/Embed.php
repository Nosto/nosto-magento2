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

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Helper\Data;

/**
 * Embed script block that includes the Nosto script in the page <head>.
 * This block should be included on all pages.
 */
class Embed extends Template
{
    /**
     * The default Nosto server address to use if none is configured.
     */
    const DEFAULT_SERVER_ADDRESS = 'connect.nosto.com';

    /**
     * @inheritdoc
     */
    protected $_template = 'embed.phtml';
    private $_accountHelper;
    private $_dataHelper;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param Account $accountHelper the account helper.
     * @param Data $dataHelper the data helper.
     * @param array $data optional data.
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        Data $dataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->_accountHelper = $accountHelper;
        $this->_dataHelper = $dataHelper;
    }

    /**
     * Returns the account name for the current store.
     *
     * @return string the account name or empty string if account is not found.
     */
    public function getAccountName()
    {
        /** @var Store $store */
        $store = $this->_storeManager->getStore(true);
        $account = $this->_accountHelper->findAccount($store);
        return !is_null($account) ? $account->getName() : '';
    }

    /**
     * Returns the Nosto server address.
     * This is taken from the local environment if it is set, or else it
     * defaults to "connect.nosto.com".
     *
     * @return string the url.
     */
    public function getServerAddress()
    {
        return gentenv('NOSTO_SERVER_URL')
            ? gentenv('NOSTO_SERVER_URL')
            : self::DEFAULT_SERVER_ADDRESS;
    }
}
