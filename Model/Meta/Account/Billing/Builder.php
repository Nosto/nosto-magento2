<?php

namespace Nosto\Tagging\Model\Meta\Account\Billing;

use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

class Builder
{
    protected $_factory;
    protected $_logger;

    /**
     * @param Factory         $factory
     * @param LoggerInterface $logger
     */
    public function __construct(Factory $factory, LoggerInterface $logger) {
        $this->_factory = $factory;
        $this->_logger = $logger;
    }

    /**
     * @param Store $store
     * @return \Nosto\Tagging\Model\Meta\Account\Billing
     */
    public function build(Store $store)
    {
        $metaData = $this->_factory->create();

        try {
            $country = $store->getConfig('general/country/default');
            if (!empty($country)) {
                $metaData->setCountry(new \NostoCountryCode($country));
            }
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
