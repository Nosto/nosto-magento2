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

namespace Nosto\Tagging\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Framework\Phrase;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Helper\Ratings as NostoRatingsHelper;

/**
 * Option array class to generate a list of selectable options that allows the merchant to choose
 * the provider for the ratings and reviews.
 *
 * @package Nosto\Tagging\Model\Config\Source
 */
class Ratings implements ArrayInterface
{
    private $nostoRatingsHelper;

    /**
     * Ratings constructor.
     * @param NostoRatingsHelper $nostoRatingsHelper
     */
    public function __construct(NostoRatingsHelper $nostoRatingsHelper)
    {
        $this->nostoRatingsHelper = $nostoRatingsHelper;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            ['value' => Data::SETTING_VALUE_NO_RATINGS, 'label' => new Phrase('No Ratings')]
        ];

        if ($this->nostoRatingsHelper->canUseYotpo()) {
            $yotpo =  ['value' => Data::SETTING_VALUE_YOTPO_RATINGS, 'label' => new Phrase('Yotpo Ratings')];
            $options[]= $yotpo;
        }

        if ($this->nostoRatingsHelper->canUseMagentoRatingsAndReviews()) {
            $mageRatings = ['value' => Data::SETTING_VALUE_MAGENTO_RATINGS, 'label' => new Phrase('Magento Ratings')];
            $options[] = $mageRatings;
        }

        return $options;
    }
}
