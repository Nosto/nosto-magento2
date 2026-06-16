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

namespace Nosto\Tagging\Model\Operation;

use Nosto\Model\Format;
use Nosto\NostoException;
use Nosto\Operation\AbstractAuthenticatedOperation;
use Nosto\Request\Api\ApiRequest;
use Nosto\Request\Api\Token;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Result\Api\JsonResultHandler;

class GetSettings extends AbstractAuthenticatedOperation
{
    /**
     * Fetches account settings from Nosto and returns the currencies map as Format objects.
     *
     * @return Format[] keyed by currency code
     * @throws NostoException
     * @throws AbstractHttpException
     */
    public function getCurrencyFormats(): array
    {
        $request = $this->initRequest(
            $this->account->getApiToken(Token::API_SETTINGS),
            $this->account->getName(),
            $this->activeDomain
        );
        $result = $request->getResultHandler()->parse($request->get());

        if (!is_array($result) || empty($result['currencies'])) {
            return [];
        }

        $formats = [];
        foreach ($result['currencies'] as $code => $data) {
            if (!isset(
                $data['currency_before_amount'],
                $data['currency_token'],
                $data['decimal_character'],
                $data['grouping_separator'],
                $data['decimal_places']
            )) {
                continue;
            }
            $formats[$code] = new Format(
                (bool)$data['currency_before_amount'],
                $data['currency_token'],
                $data['decimal_character'],
                $data['grouping_separator'],
                (int)$data['decimal_places']
            );
        }

        return $formats;
    }

    /** @inheritdoc */
    protected function getResultHandler()
    {
        return new JsonResultHandler();
    }

    /** @inheritdoc */
    protected function getRequestType()
    {
        return new ApiRequest();
    }

    /** @inheritdoc */
    protected function getContentType()
    {
        return self::CONTENT_TYPE_APPLICATION_JSON;
    }

    /** @inheritdoc */
    protected function getPath()
    {
        return ApiRequest::PATH_SETTINGS;
    }
}
