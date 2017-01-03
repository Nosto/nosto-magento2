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
use Nosto\Tagging\Model\Meta\Oauth\Builder as NostoOauthBuilder;
use NostoOAuthClient;

class Sync extends Base
{
    const ADMIN_RESOURCE = 'Nosto_Tagging::system_nosto_account';

    /**
     * @var Json
     */
    protected $result;
    private $nostoHelperAccount;
    private $oauthMetaBuilder;
    private $storeManager;

    /**
     * @param Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoOauthBuilder $oauthMetaBuilder
     * @param StoreManagerInterface $storeManager
     * @param Json $result
     */
    public function __construct(
        Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoOauthBuilder $oauthMetaBuilder,
        StoreManagerInterface $storeManager,
        Json $result
    ) {
        parent::__construct($context);

        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->oauthMetaBuilder = $oauthMetaBuilder;
        $this->storeManager = $storeManager;
        $this->result = $result;
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
            $metaData = $this->oauthMetaBuilder->build($store, $account);
            $client = new NostoOAuthClient($metaData);

            $response['success'] = true;
            $response['redirect_url'] = $client->getAuthorizationUrl();
        }

        return $this->result->setData($response);
    }
}
