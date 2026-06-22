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
use Nosto\Exception\Builder as ExceptionBuilder;
use Nosto\Nosto;
use Nosto\Operation\AbstractAuthenticatedOperation;
use Nosto\Request\Api\ApiRequest;
use Nosto\Request\Http\Exception\AbstractHttpException;
use Nosto\Request\Http\HttpRequest;
use Nosto\Result\Api\JsonResultHandler;

class GetSettings extends AbstractAuthenticatedOperation
{
    private const SETTINGS_PATH = '%s/include/%s/settings.json';

    /**
     * Fetches account settings from Nosto and returns the currency settings map as Format objects.
     *
     * @return Format[] keyed by currency code
     * @throws AbstractHttpException
     */
    public function getCurrencyFormats(): array
    {
        $serverUrl = Nosto::getServerUrl();
        if (!$serverUrl) {
            return [];
        }

        $request = new HttpRequest();
        $request->setUrl($this->buildSettingsUrl($serverUrl));

        $response = $request->get();
        if ($response->getCode() !== 200) {
            throw ExceptionBuilder::fromHttpRequestAndResponse($request, $response);
        }

        $result = $response->getJsonResult(true);
        if (!is_array($result)) {
            return [];
        }

        $currencies = $result['currency_settings'] ?? $result['currencySettings'] ?? [];
        if (!is_array($currencies)) {
            return [];
        }

        $formats = [];
        foreach ($currencies as $code => $data) {
            if (!is_array($data)) {
                continue;
            }

            $currencyBeforeAmount = $data['currency_before_amount'] ?? $data['currencyBeforeAmount'] ?? null;
            $currencyToken = $data['currency_token'] ?? $data['currencyToken'] ?? null;
            $decimalCharacter = $data['decimal_character'] ?? $data['decimalCharacter'] ?? null;
            $groupingSeparator = $data['grouping_separator'] ?? $data['groupingSeparator'] ?? null;
            $decimalPlaces = $data['decimal_places'] ?? $data['decimalPlaces'] ?? null;
            if ($currencyBeforeAmount === null
                || $currencyToken === null
                || $decimalCharacter === null
                || $decimalPlaces === null
            ) {
                continue;
            }

            $formats[$code] = new Format(
                (bool)$currencyBeforeAmount,
                $currencyToken,
                $decimalCharacter,
                $groupingSeparator,
                (int)$decimalPlaces
            );
        }

        return $formats;
    }

    /**
     * @return string
     */
    private function buildSettingsUrl(string $serverUrl): string
    {
        return sprintf(
            self::SETTINGS_PATH,
            $this->getConnectBaseUrl($serverUrl),
            rawurlencode($this->account->getName())
        );
    }

    /**
     * @param string $serverUrl
     * @return string
     */
    private function getConnectBaseUrl(string $serverUrl): string
    {
        if (strpos($serverUrl, 'http://') === 0 || strpos($serverUrl, 'https://') === 0) {
            return rtrim($serverUrl, '/');
        }

        return 'https://' . rtrim($serverUrl, '/');
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
