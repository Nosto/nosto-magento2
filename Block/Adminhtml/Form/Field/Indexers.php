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

namespace Nosto\Tagging\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Product\Cache\CacheRepository;

class Indexers extends Field
{
    /** @var Http $request */
    public $request;

    /** @var CacheRepository $cacheRepository */
    public $cacheRepository;

    /** @var NostoHelperScope $nostoHelperScope */
    public $nostoHelperScope;

    /**
     * Indexers block constructor.
     * @param Context $context
     * @param Http $request
     * @param CacheRepository $cacheRepository
     * @param NostoHelperScope $nostoHelperScope
     * @param array $data
     */
    public function __construct(
        Context $context,
        Http $request,
        CacheRepository $cacheRepository,
        NostoHelperScope $nostoHelperScope,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->request = $request;
        $this->cacheRepository = $cacheRepository;
        $this->nostoHelperScope = $nostoHelperScope;
    }

    /**
     * Return the amount of products marked as dirty
     * @return int
     */
    public function getAmountDirtyProducts()
    {
        $id = (int)$this->request->getParam('store');
        $store = $this->nostoHelperScope->getStore($id);
        return $this->cacheRepository->getTotalDirty($store);
    }

    /**
     * return the amount of products marked as out of sync
     * @return int
     */
    public function getAmountOutOfSyncProducts()
    {
        $id = (int)$this->request->getParam('store');
        $store = $this->nostoHelperScope->getStore($id);
        return $this->cacheRepository->getTotalOutOfSync($store);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element) //@codingStandardsIgnoreLine
    {
        return $this->toHtml();
    }

    /**
     * @return $this|Field
     */
    protected function _prepareLayout() //@codingStandardsIgnoreLine
    {
        parent::_prepareLayout();
        $this->setTemplate('indexersStatuses.phtml');
        return $this;
    }
}
