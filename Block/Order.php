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

use Magento\Checkout\Block\Success;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;
use Nosto\Helper\DateHelper;
use Nosto\Helper\PriceHelper;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Model\Order\Builder as NostoOrderBuilder;

/**
 * Category block used for outputting meta-data on the stores category pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Order extends Success
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

    private $nostoOrderBuilder;
    private $checkoutSession;

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * Constructor.
     *
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param NostoOrderBuilder $orderBuilder
     * @param Session $checkoutSession
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param array $data
     */
    public function __construct(
        Context $context,
        /** @noinspection PhpUndefinedClassInspection */
        OrderFactory $orderFactory,
        NostoOrderBuilder $orderBuilder,
        Session $checkoutSession,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        array $data = []
    ) {
        parent::__construct($context, $orderFactory, $data);

        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->checkoutSession = $checkoutSession;
        $this->nostoOrderBuilder = $orderBuilder;
    }

    /**
     * Returns the Nosto order meta-data model.
     *
     * @return \Nosto\Object\Order\Order the order meta data model.
     */
    public function getAbstractObject()
    {
        /** @var \Magento\Sales\Model\Order $order */
        return $this->nostoOrderBuilder->build($this->checkoutSession->getLastRealOrder());
    }

    /**
     * Formats a price e.g. "1234.56".
     *
     * @param int $price the price to format.
     * @return string the formatted price.
     */
    public function formatNostoPrice($price)
    {
        return PriceHelper::format($price);
    }

    /**
     * Formats a date, e.g. "2015-12-24";
     *
     * @param string $date the date to format.
     * @return string the formatted date.
     */
    public function formatNostoDate($date)
    {
        return DateHelper::format($date);
    }
}
