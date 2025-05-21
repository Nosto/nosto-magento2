<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;

class MonitoringLogin extends Template
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }

    public function getLoginFormAction(string $url): string
    {
        return $this->getUrl($url, ['_secure' => true]);
    }
}