<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nosto\Tagging\Helper\Account;
use Magento\Framework\Webapi\Rest\Response;

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

    /** @var Response $response */
    private Response $response;

    public function __construct(
        ManagerInterface $messageManager,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Account $accountHelper,
        RedirectFactory $redirectFactory,
        Response $response
    ) {
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->accountHelper = $accountHelper;
        $this->redirectFactory = $redirectFactory;
        $this->response = $response;
    }

    public function execute()
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $account = $this->accountHelper->findAccount($store);
        $request = $this->request->getParams();

        if ('3l6N8ignodufMWmz5KcOegBttCJIQDmsFB7P6qmN3MRI7BJyruhsdhm9hjqrlzBz' !== $request['token']) {
            $this->messageManager->addErrorMessage('Invalid token!');

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
        }

        $this->response->setHeader('NOSTO-DEBUG-TOKEN', $request['token']);
        dump($this->response->getHeader('NOSTO-DEBUG-TOKEN'), 22);
//        $this->response->clearHeader('NOSTO-DEBUG-TOKEN');
//        dump($this->response->getHeader('NOSTO-DEBUG-TOKEN'), 24);
    }
}