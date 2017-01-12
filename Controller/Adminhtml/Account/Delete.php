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

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Model\User\Builder as NostoCurrentUserBuilder;
use Nosto\Tagging\Model\Meta\Account\Iframe\Builder as NostoIframeMetaBuilder;
use NostoHelperIframe;
use NostoMessage;

class Delete extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $result;
    private $storeManager;
    private $nostoHelperAccount;
    private $nostoCurrentUserBuilder;
    private $nostoIframeMetaBuilder;

    /**
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoIframeMetaBuilder $nostoIframeMetaBuilder
     * @param NostoCurrentUserBuilder $nostoCurrentUserBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoIframeMetaBuilder $nostoIframeMetaBuilder,
        NostoCurrentUserBuilder $nostoCurrentUserBuilder,
        StoreManagerInterface $storeManager,
        Json $result
    ) {
        parent::__construct($context);

        $this->nostoIframeMetaBuilder = $nostoIframeMetaBuilder;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->storeManager = $storeManager;
        $this->result = $result;
        $this->nostoCurrentUserBuilder = $nostoCurrentUserBuilder;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $response = ['success' => false];

        $storeId = $this->_request->getParam('store');
        /** @var Store $store */
        $store = $this->storeManager->getStore($storeId);
        $account = !is_null($store)
            ? $this->nostoHelperAccount->findAccount($store)
            : null;

        if (!is_null($store) && !is_null($account)) {
            $currentUser = $this->nostoCurrentUserBuilder->build();
            if ($this->nostoHelperAccount->deleteAccount($account, $store, $currentUser)) {
                $response['success'] = true;
                $response['redirect_url'] = NostoHelperIframe::getUrl(
                    $this->nostoIframeMetaBuilder->build($store),
                    null, // we don't have an account anymore
                    $this->nostoCurrentUserBuilder->build(),
                    [
                        'message_type' => NostoMessage::TYPE_SUCCESS,
                        'message_code' => NostoMessage::CODE_ACCOUNT_DELETE,
                    ]
                );
            }
        }

        if (!$response['success']) {
            $response['redirect_url'] = NostoHelperIframe::getUrl(
                $this->nostoIframeMetaBuilder->build($store),
                $account,
                $this->nostoCurrentUserBuilder->build(),
                [
                    'message_type' => NostoMessage::TYPE_ERROR,
                    'message_code' => NostoMessage::CODE_ACCOUNT_DELETE,
                ]
            );
        }

        return $this->result->setData($response);
    }
}
