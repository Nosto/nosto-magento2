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
use Nosto\Tagging\Helper\Account as NostoHelperAccount;

/**
 * Cart block used for cart tagging.
 */
class Knockout extends Template
{

    /**
     * @var NostoHelperAccount
     */
    protected $nostoHelperAccount;

    /**
     * Constructor
     *
     * @param Template\Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        NostoHelperAccount $nostoHelperAccount,
        array $data = []
    )
    {

        parent::__construct($context, $data);
        $this->nostoHelperAccount = $nostoHelperAccount;
    }


    public function getTemplate()
    {
        $template = null;
        if ($this->nostoEnabled()) {
            $template = parent::getTemplate();
        }

        return $template;
    }

    public function getJsLayout()
    {
        $jsLayout = null;
        if ($this->nostoEnabled()) {
            $jsLayout = parent::getJsLayout();
        }

        return $jsLayout;
    }

    private function nostoEnabled() {
        $enabled = false;
        if ($this->nostoHelperAccount->nostoInstalledAndEnabled(
            $this->_storeManager->getStore()
        )) {
            $enabled= true;
        }

        return $enabled;
    }
}
