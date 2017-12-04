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

namespace Nosto\Tagging\Helper;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\PageCache\Model\Cache\Type;
use Magento\PageCache\Model\Config;

/**
 * Cache helper class for cache related tasks.
 */
class Cache extends AbstractHelper
{
    const CACHE_ID_LAYOUT = 'layout';

    /** @var Config $config */
    private $config;

    /** @var TypeListInterface $typeList */
    private $typeList;

    private $cacheState;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param Config $config
     * @param TypeListInterface $typeList
     * @param StateInterface $cacheState
     */
    public function __construct(
        Context $context,
        Config $config,
        TypeListInterface $typeList,
        StateInterface $cacheState
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->typeList = $typeList;
        $this->cacheState = $cacheState;
    }

    /**
     * Invalidate full page cache
     *
     * @suppress PhanDeprecatedFunction
     */
    public function invalidatePageCache()
    {
        if ($this->config->isEnabled()) {
            $this->typeList->invalidate(Type::TYPE_IDENTIFIER);
        }
    }

    /**
     * Invalidate layout cache
     */
    public function invalidateLayoutCache()
    {
        if ($this->cacheState->isEnabled(self::CACHE_ID_LAYOUT)) {
            $this->typeList->invalidate(self::CACHE_ID_LAYOUT);
        }
    }
}
