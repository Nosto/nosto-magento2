<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
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
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Magento\Customer\Model\Session\Proxy as CustomerSession;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Customer helper
 * @package Nosto\Tagging\Helper
 */
class Customer extends AbstractHelper
{
    private $customerSession;
    private $groupRepository;

    /**
     * Customer constructor.
     *
     * @param CustomerSession $customerSession
     * @param GroupRepository $groupRepository
     */
    public function __construct(
        CustomerSession $customerSession,
        GroupRepository $groupRepository
    ) {
        $this->customerSession = $customerSession;
        $this->groupRepository = $groupRepository;
    }

    /**
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getGroupCode()
    {
        $customerGroupId = $this->getGroupId();
        if ($customerGroupId) {
            $group = $this->groupRepository->getById($customerGroupId);
            return $group->getCode();
        }
        return $this->groupRepository->getById(Variation::DEFAULT_CUSTOMER_GROUP_ID)->getCode();
    }

    /**
     * @return int|null
     */
    public function getGroupId()
    {
        if ($this->customerSession->isLoggedIn()
            && $this->customerSession->getCustomer()
        ) {
            return $this->customerSession->getCustomer()->getGroupId();
        }
        return null;
    }
}
