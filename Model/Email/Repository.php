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

namespace Nosto\Tagging\Model\Email;

use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Magento\Newsletter\Model\Subscriber;

/**
 * Repository wrapper / helper class for fetching marketing permission related items
 *
 * @package Nosto\Tagging\Model\Email
 */
class Repository
{
    /**
     * @var SubscriberResource
     */
    private $subscriber;

    /**
     * Repository constructor.
     * @param SubscriberResource $subscriber
     */
    public function __construct(
        SubscriberResource $subscriber
    ) {
        $this->subscriber = $subscriber;
    }

    /**
     * Gets newsletter subscription by email
     * @param $email
     * @return array
     */
    public function getNewsletterOptInForEmail($email)
    {
        return $this->subscriber->loadByEmail($email);
    }

    /**
     * Checks if email is opted in / marketing permission has been given
     *
     * @param $email
     * @return bool
     */
    public function isOptedIn($email)
    {
        $subscriber = $this->getNewsletterOptInForEmail($email);
        if (!$subscriber || empty($subscriber)) {
            return false;
        }

        if (isset($subscriber['subscriber_status'])
            && (int)$subscriber['subscriber_status'] === Subscriber::STATUS_SUBSCRIBED
        ) {
            return true;
        }

        return false;
    }
}
