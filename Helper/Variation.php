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

namespace Nosto\Tagging\Helper;

use Exception;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface as GroupRepository;
use Magento\Customer\Model\Data\Group;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nosto\Tagging\Logger\Logger;

/**
 * Variation helper
 */
class Variation extends AbstractHelper
{
    public const DEFAULT_CUSTOMER_GROUP_ID = GroupManagement::NOT_LOGGED_IN_ID;

    /** @var GroupRepository */
    private GroupRepository $groupRepository;

    /** @var Logger */
    private Logger $logger;

    /**
     * Variation constructor.
     * @param Context $context
     * @param GroupRepository $groupRepository
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        GroupRepository $groupRepository,
        Logger $logger
    ) {
        parent::__construct($context);
        $this->groupRepository = $groupRepository;
        $this->logger = $logger;
    }

    /**
     * @return GroupInterface|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getDefaultGroupVariation()
    {
        $defaultGroup = $this->groupRepository->getById(self::DEFAULT_CUSTOMER_GROUP_ID);
        if ($defaultGroup instanceof Group) {
            return $defaultGroup;
        }
        return null;
    }

    /**
     * @return int|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getDefaultVariationId()
    {
        return $this->getDefaultGroupVariation() ? $this->getDefaultGroupVariation()->getId() : null;
    }

    /**
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getDefaultVariationCode()
    {
        return $this->getDefaultGroupVariation() ? $this->getDefaultGroupVariation()->getCode() : null;
    }

    /**
     * Checks if the code is the default variation
     *
     * @param string $code
     * @return bool
     */
    public function isDefaultVariationCode(string $code)
    {
        try {
            if ($code === $this->getDefaultVariationCode()) {
                return true;
            }
        } catch (Exception $e) {
            $this->logger->exception($e);
            return false;
        }

        return false;
    }
}
