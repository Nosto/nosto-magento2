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

namespace Nosto\Tagging\Model\Person;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Nosto\Model\AbstractPerson;
use Nosto\Model\ModelFilter;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Model\Email\Repository as NostoEmailRepository;

abstract class Builder
{
    /**
     * @var NostoEmailRepository
     */
    private NostoEmailRepository $emailRepository;
    /**
     * @var EventManager
     */
    private EventManager $eventManager;
    /**
     * @var NostoHelperData
     */
    private NostoHelperData $nostoHelperData;

    /**
     * Builder constructor.
     * @param NostoEmailRepository $emailRepository
     * @param EventManager $eventManager
     * @param NostoHelperData $nostoHelperData
     */
    public function __construct(
        NostoEmailRepository $emailRepository,
        EventManager $eventManager,
        NostoHelperData $nostoHelperData
    ) {
        $this->emailRepository = $emailRepository;
        $this->eventManager = $eventManager;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string|null $phone
     * @param string|null $postCode
     * @param string|null $country
     * @param string|null $customerGroup
     * @param string|null $dateOfBirth
     * @param string|null $gender
     * @param string|null $customerReference
     *
     * @return AbstractPerson|null
     */
    public function build(
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone = null,
        ?string $postCode = null,
        ?string $country = null,
        ?string $customerGroup = null,
        ?string $dateOfBirth = null,
        ?string $gender = null,
        ?string $customerReference = null
    ) {
        if (!$this->nostoHelperData->isSendCustomerDataToNostoEnabled()) {
            return null;
        }
        $modelFilter = new ModelFilter();
        $this->eventManager->dispatch(
            'nosto_person_load_before',
            [
                'modelFilter' => $modelFilter,
                'fields' => [
                    'firstName' => $firstName,
                    'lastLane' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'postCode' => $postCode,
                    'country' => $country
                ]
            ]
        );
        if (!$modelFilter->isValid()) {
            return null;
        }
        $person = $this->buildObject(
            $firstName,
            $lastName,
            $email,
            $phone,
            $postCode,
            $country,
            $customerGroup,
            $dateOfBirth,
            $gender,
            $customerReference
        );
        $person->setMarketingPermission(
            $this->emailRepository->isOptedIn($person->getEmail())
        );
        $this->eventManager->dispatch('nosto_person_load_after', [
            'modelFilter' => $modelFilter,
            'person' => $person
        ]);
        if (!$modelFilter->isValid()) {
            return null;
        }

        return $person;
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string|null $phone
     * @param string|null $postCode
     * @param string|null $country
     * @param string|null $customerGroup
     * @param string|null $dateOfBirth
     * @param string|null $gender
     * @param string|null $customerReference
     *
     * @return AbstractPerson
     */
    abstract public function buildObject(
        string $firstName,
        string $lastName,
        string $email,
        string $phone = null,
        string $postCode = null,
        string $country = null,
        string $customerGroup = null,
        string $dateOfBirth = null,
        string $gender = null,
        string $customerReference = null
    );
}
