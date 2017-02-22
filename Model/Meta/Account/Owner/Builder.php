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

namespace Nosto\Tagging\Model\Meta\Account\Owner;

use Magento\Backend\Model\Auth\Session;
use Magento\User\Model\User;
use Nosto\Sdk\NostoOwner;
use Psr\Log\LoggerInterface;

class Builder
{
    protected $_logger;

    /**
     * @param Session $backendAuthSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session $backendAuthSession,
        LoggerInterface $logger
    ) {
        $this->_backendAuthSession = $backendAuthSession;
        $this->_logger = $logger;
    }

    /**
     * @return NostoOwner
     */
    public function build()
    {
        $metaData = new NostoOwner();

        try {
            /** @var User $user */
            $user = $this->_backendAuthSession->getUser();
            if (!is_null($user)) {
                $metaData->setFirstName($user->getFirstName());
                $metaData->setLastName($user->getLastName());
                $metaData->setEmail($user->getEmail());
            }
        } catch (\Nosto\Sdk\NostoException $e) {
            $this->_logger->error($e, ['exception' => $e]);
        }

        return $metaData;
    }
}
