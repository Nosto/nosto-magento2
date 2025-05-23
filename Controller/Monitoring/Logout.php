<?php

namespace Nosto\Tagging\Controller\Monitoring;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Nosto\Tagging\Helper\Cache;

class Logout implements ActionInterface
{
    /** @var RedirectFactory $redirectFactory */
    private RedirectFactory $redirectFactory;

    /** @var ManagerInterface $messageManager */
    private ManagerInterface $messageManager;

    /** @var Cache $cache */
    private Cache $cache;

    public function __construct(
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        Cache $cache
    ) {
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->cache = $cache;
    }

    public function execute(): Redirect
    {
        unset($_SESSION['nosto_debbuger_session']);

        $this->cache->flushCache();

        $this->messageManager->addSuccessMessage(__('You have been logged out.'));

        return $this->redirectFactory->create()->setUrl('/nosto/monitoring/login');
    }
}