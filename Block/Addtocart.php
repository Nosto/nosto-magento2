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

namespace Nosto\Tagging\Block;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;

/**
 * Embed script block that includes the Nosto script in the page <head> to be included on all pages.
 */
class Addtocart extends Template
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

    private $urlEncoder;
    private $nostoHelperData;

    /**
     * Constructor.
     *
     * @param Context $context
     * @param EncoderInterface $urlEncoder
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperData $nostoHelperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        EncoderInterface $urlEncoder,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoHelperData $nostoHelperData,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->urlEncoder = $urlEncoder;
        $this->nostoHelperData = $nostoHelperData;
    }

    /**
     * Retrieve url for add product to cart
     *
     * @return  string
     */
    public function getSubmitUrl()
    {
        $continueUrl = $this->urlEncoder->encode($this->_urlBuilder->getCurrentUrl());
        $activeStore = $this->nostoHelperScope->getStore(true);
        $routeParams = [ActionInterface::PARAM_NAME_URL_ENCODED => $continueUrl];
        $routeParams['_secure'] = $this->getRequest()->isSecure();
        $routeParams['_scope'] = $activeStore->getCode();
        $routeParams['_scope_to_url'] = $this->nostoHelperData->getStoreCodeToUrl($activeStore);
        $request = $this->getRequest();
        if ($request instanceof Http
            && $request->getRouteName() === 'checkout'
            && $request->getControllerName() === 'cart'
        ) {
            $routeParams['in_cart'] = 1;
        }
        return $this->_urlBuilder->getUrl('checkout/cart/add', $routeParams);
    }

    /**
     *
     * @return null
     */
    public function getAbstractObject()
    {
        return null;
    }
}
