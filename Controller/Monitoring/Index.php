<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements ActionInterface
{
    /** @var PageFactory $pageFactory */
    private PageFactory $pageFactory;

    /** @var CookieManagerInterface $cookieManager */
    private CookieManagerInterface $cookieManager;

    /** @var CookieMetadataFactory $cookieMetadataFactory */
    protected CookieMetadataFactory $cookieMetadataFactory;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    public function __construct(
        PageFactory $pageFactory,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        ManagerInterface $messageManager,
        RedirectFactory $redirectFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
    }

    public function execute()
    {
//        $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
//            ->setPath('/')
//            ->setDomain('')
//            ->setDuration(-3600)
//            ->setHttpOnly(true)
//            ->setSecure(false);
//        $this->cookieManager->deleteCookie('nosto_debugger_cookie', $cookieMetadata);
//
//        if (null === $this->cookieManager->getCookie('nosto_debugger_cookie')) {
//            $this->messageManager->addErrorMessage('Please login to continue!');
//
//            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
//        }
//        unset($_SESSION['nosto_debbuger_session']);

        if (!isset($_SESSION['nosto_debbuger_session'])) {
            $this->messageManager->addErrorMessage('Please login to continue!');

            return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set('Nosto Debugger');

        return $page;
    }
}
