<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;

class MonitoringPage extends Template
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getLogoutFormAction(): string
    {
        return $this->getUrl('nosto/monitoring/logout', ['_secure' => true]);
    }

    public function getDownloadLogFilesFormAction(): string
    {
        return $this->getUrl('nosto/monitoring/logs', ['_secure' => true]);
    }
}
