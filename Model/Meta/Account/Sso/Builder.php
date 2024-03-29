<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Model\Meta\Account\Sso;

use Exception;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Event\ManagerInterface;
use Nosto\Model\Signup\Owner;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Builder
{
    private NostoLogger $logger;
    private Session $backendAuthSession;
    private ManagerInterface $eventManager;

    /**
     * @param Session $backendAuthSession
     * @param NostoLogger $logger
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        Session $backendAuthSession,
        NostoLogger $logger,
        ManagerInterface $eventManager
    ) {
        $this->backendAuthSession = $backendAuthSession;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @return Owner
     */
    public function build()
    {
        $owner = new Owner();

        try {
            $user = $this->backendAuthSession->getUser();
            if ($user !== null) {
                $owner->setFirstName($user->getFirstName());
                $owner->setLastName($user->getLastName());
                $owner->setEmail($user->getEmail());
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
        }

        $this->eventManager->dispatch('nosto_sso_load_after', ['sso' => $owner]);

        return $owner;
    }
}
