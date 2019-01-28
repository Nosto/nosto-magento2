<?php
/**
 * Created by PhpStorm.
 * User: olsiqose
 * Date: 25/01/2019
 * Time: 16.37
 */

namespace Nosto\Tagging\Block\Adminhtml\Form\Field;

use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;

class Tokens extends Field
{
    /** @var NostoHelperAccount  */
    public $nostoHelperAccount;

    /** @var NostoHelperScope  */
    public $nostoHelperScope;

    /**
     * Tokens constructor.
     * @param Context $context
     * @param array $data
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     */
    public function __construct(Context $context, array $data = [], NostoHelperAccount $nostoHelperAccount, NostoHelperScope $nostoHelperScope)
    {
        parent::__construct($context, $data, $nostoHelperAccount, $nostoHelperScope);
        $this->setTemplate('tokens.phtml');
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * Get the Nosto account details
     *
     * @return \Nosto\Object\Signup\Account|null
     */
    public function getAccountDetails()
    {
        $store = $this->nostoHelperScope->getStore();
        $account = $this->nostoHelperAccount->findAccount($store);
        return $account;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->toHtml();
    }
}