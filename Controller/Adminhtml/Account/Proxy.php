<?php

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
    ) {
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
