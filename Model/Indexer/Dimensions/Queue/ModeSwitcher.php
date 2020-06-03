<?php /** @noinspection DuplicatedCode */
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

namespace Nosto\Tagging\Model\Indexer\Dimensions\Queue;

use Magento\Indexer\Model\DimensionMode;
use Magento\Indexer\Model\DimensionModes;
use Nosto\Tagging\Model\Indexer\Dimensions\ModeSwitcherInterface;

class ModeSwitcher implements ModeSwitcherInterface
{
    /**
     * @var DimensionModeConfiguration
     */
    private $dimensionModeConfiguration;

    /**
     * @var ModeSwitcherConfiguration
     */
    private $modeSwitcherConfiguration;

    /**
     * ModeSwitcher constructor.
     * @param DimensionModeConfiguration $dimensionModeConfiguration
     * @param ModeSwitcherConfiguration $modeSwitcherConfiguration
     */
    public function __construct(
        DimensionModeConfiguration $dimensionModeConfiguration,
        ModeSwitcherConfiguration $modeSwitcherConfiguration
    ) {
        $this->dimensionModeConfiguration = $dimensionModeConfiguration;
        $this->modeSwitcherConfiguration = $modeSwitcherConfiguration;
    }

    /**
     * @inheritDoc
     */
    public function getDimensionModes(): DimensionModes
    {
        $dimensionsList = [];
        foreach ($this->dimensionModeConfiguration->getDimensionModes() as $dimension => $modes) {
            $dimensionsList[] = new DimensionMode($dimension, $modes);
        }

        return new DimensionModes($dimensionsList);
    }

    /**
     * @inheritDoc
     */
    public function switchMode(string $currentMode, string $previousMode) // @codingStandardsIgnoreLine
    {
        $this->modeSwitcherConfiguration->saveMode($currentMode);
    }

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->dimensionModeConfiguration->getCurrentMode();
    }
}
