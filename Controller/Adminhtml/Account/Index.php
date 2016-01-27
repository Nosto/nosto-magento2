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
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 *
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);

        $this->_resultPageFactory = $resultPageFactory;
        $this->_storeManager = $storeManager;
    }

    /**
     * @return Page
     */
    public function execute()
    {
        if (!$this->getSelectedStore()) {
            // If we are not under a store view, then redirect to the first
            // found one. Nosto is configured per store.
            foreach ($this->_storeManager->getWebsites() as $website) {
                $storeId = $website->getDefaultGroup()->getDefaultStoreId();
                if (!empty($storeId)) {
                    // todo: use redirect result model
                    $this->_redirect('*/*/index', ['store' => $storeId]);
                    /** @noinspection PhpInconsistentReturnPointsInspection */
                    return;
                }
            }
        }

        /** @var Page $result */
        $result = $this->_resultPageFactory->create();
        $result->setActiveMenu('Nosto_Tagging::system_nosto_account');
        $result->getConfig()->getTitle()->prepend(
            __('Nosto - Account Settings')
        );
        return $result;
    }

    /**
     * Returns the currently selected store.
     * If it is single store setup, then just return the default store.
     * If it is a multi store setup, the expect a store id to passed in the
     * request params and return that store as the current one.
     *
     * @return Store|null the store or null if not found.
     */
    protected function getSelectedStore()
    {
        if ($this->_storeManager->isSingleStoreMode()) {
            return $this->_storeManager->getStore(true);
        } elseif (($storeId = $this->_storeManager->getStore()->getId())) {
            return $this->_storeManager->getStore($storeId);
        } else {
            return null;
        }
    }
}
