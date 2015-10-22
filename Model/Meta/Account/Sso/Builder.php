<?php

namespace Nosto\Tagging\Model\Meta\Account\Sso;

use Magento\Backend\Model\Auth\Session;
use Psr\Log\LoggerInterface;

class Builder
{
    protected $_factory;
    protected $_logger;

    /**
     * @param Factory         $factory
     * @param Session         $backendAuthSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Factory $factory,
        Session $backendAuthSession,
        LoggerInterface $logger
    ) {
        $this->_factory = $factory;
        $this->_backendAuthSession = $backendAuthSession;
        $this->_logger = $logger;
    }

    /**
     * @return \Nosto\Tagging\Model\Meta\Account\Sso
     */
    public function build()
    {
        $metaData = $this->_factory->create();

        try {
            $user = $this->_backendAuthSession->getUser();
            if (!is_null($user)) {
                $metaData->setFirstName($user->getFirstname());
                $metaData->setLastName($user->getLastname());
                $metaData->setEmail($user->getEmail());
            }
        } catch (\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
