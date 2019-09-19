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

namespace Nosto\Tagging\Model\Indexer\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;
use Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider;
use Magento\Store\Model\StoreDimensionProvider;

class DimensionModeConfiguration
{
    /**
     * Available modes of dimensions for nosto product data indexer
     */
    const DIMENSION_NONE = 'none';
    const DIMENSION_STORE = 'store';
    /**#@-*/

    /**
     * Mapping between dimension mode and dimension provider name
     *
     * @var array
     */
    private $modesMapping = [
        self::DIMENSION_NONE => [
        ],
        self::DIMENSION_STORE => [
            StoreDimensionProvider::DIMENSION_NAME
        ]
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var string
     */
    private $currentMode;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Return dimension modes configuration.
     *
     * @return array
     */
    public function getDimensionModes(): array
    {
        return $this->modesMapping;
    }

    /**
     * Get names of dimensions which used for provided mode.
     * By default return dimensions for current enabled mode
     *
     * @param string|null $mode
     * @return string[]
     * @throws \InvalidArgumentException
     */
    public function getDimensionConfiguration(string $mode = null): array
    {
        if ($mode && !isset($this->modesMapping[$mode])) {
            throw new \InvalidArgumentException(
                sprintf('Undefined dimension mode "%s".', $mode)
            );
        }
        return $this->modesMapping[$mode ?? $this->getCurrentMode()];
    }

    /**
     * @return string
     */
    private function getCurrentMode(): string
    {
        if (null === $this->currentMode) {
            $this->currentMode = $this->scopeConfig->getValue(ModeSwitcherConfiguration::XML_PATH_PRICE_DIMENSIONS_MODE)
                ?: self::DIMENSION_NONE;
        }

        return $this->currentMode;
    }
}