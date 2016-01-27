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

namespace Nosto\Tagging\Controller\Adminhtml\Account;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;

/**
 *
 */
class Proxy extends Action
{
    /**
     * @inheritdoc
     */
    protected $_publicActions = ['proxy'];

    /**
     * @param Context $context
     * @param Session $backendAuthSession
     */
    public function __construct(
        Context $context,
        Session $backendAuthSession
    )
    {
        parent::__construct($context);

        $this->_backendAuthSession = $backendAuthSession;
    }

    /**
     * Action that acts as a proxy to the account/index page, when the frontend
     * oauth controller redirects the admin user back to the backend after
     * finishing the oauth authorization cycle.
     * This is a workaround as you cannot redirect directly to a protected
     * action in the backend end from the front end. The action also handles
     * passing along any error/success messages.
     *
     * @return void
     */
    public function execute()
    {
        $type = $this->_request->getParam('message_type');
        $code = $this->_request->getParam('message_code');
        $text = $this->_request->getParam('message_text');
        if (!is_null($type) && !is_null($code)) {
            $this->_backendAuthSession->setData(
                'nosto_message',
                [
                    'message_type' => $type,
                    'message_code' => $code,
                    'message_text' => $text,
                ]
            );
        }

        $params = [];
        if (($storeId = (int)$this->_request->getParam('store')) !== 0) {
            $params['store'] = $storeId;
        }

        // todo: proper way to redirect?
        $this->_redirect('*/*/index', $params);
    }
}
