<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Block;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\View\Element\Template\Context;
use Magento\LayeredNavigation\Block\Navigation\State;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Filter as NostoFilter;
use Nosto\Tagging\Model\Filter\Builder as NostoFilterBuilder;

class Filter extends State
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct;
    }

    private $categoryBuilder;
    private $categoryRepository;
    private $nostoFilterBuilder;

    /**
     * Filter constructor.
     * @param Context $context
     * @param LayerResolver $layerResolver
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoFilterBuilder $nostoFilterBuilder
     * @param array $data
     */
    public function __construct(
        Context $context,
        LayerResolver $layerResolver,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoFilterBuilder $nostoFilterBuilder,
        array $data = []
    ) {
        parent::__construct($context, $layerResolver, $data);

        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->nostoFilterBuilder = $nostoFilterBuilder;
    }

    /**
     * Returns the current active filters
     *
     * @return NostoFilter
     */
    public function getNostoFilter()
    {
        $filters = $this->getActiveFilters();

        if (!$filters) {
            return null;
        }

        return $this->nostoFilterBuilder->build($filters);
    }
}
