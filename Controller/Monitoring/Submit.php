<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\NostoException;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Tagging\Helper\Account;
use Nosto\Model\Signup\Account as SignupAccount;
use Nosto\Request\Api\Token as NostoToken;
use Nosto\Tagging\Model\MockOperation\MockUpsertProduct;

class Submit implements ActionInterface
{
    /** @var RequestInterface $request */
    private RequestInterface $request;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var StoreManagerInterface $storeManager */
    private StoreManagerInterface $storeManager;

    /** @var Account $accountHelper */
    private Account $accountHelper;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var CookieManagerInterface  */
    private CookieManagerInterface $cookieManager;

    /** @var CookieMetadataFactory $cookieMetadataFactory */
    private CookieMetadataFactory $cookieMetadataFactory;

    public function __construct(
        ManagerInterface $messageManager,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Account $accountHelper,
        RedirectFactory $redirectFactory,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
    ) {
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->accountHelper = $accountHelper;
        $this->redirectFactory = $redirectFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * @throws NostoException
     * @throws NoSuchEntityException
     * @throws AbstractHttpException
     */
    public function execute()
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $account = $this->accountHelper->findAccount($store);
        $request = $this->request->getParams();

        $signupAccount = new SignupAccount($account->getName());

        $signupAccount->addApiToken(new NostoToken(NostoToken::API_PRODUCTS, $request['token']));
        $result = (new MockUpsertProduct($signupAccount))->upsert();
        if (false === $result['success']) {
            $this->messageManager->addErrorMessage($result['message']);

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
        }

//        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
//            ->setPath('/')
//            ->setHttpOnly(true)
//            ->setSecure(false);
//        $this->cookieManager->setPublicCookie('nosto_debugger_cookie', $request['token'], $metadata);
        $_SESSION['nosto_debbuger_session'] = $request['token'];

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/');
    }
}