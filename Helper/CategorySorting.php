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

namespace Nosto\Tagging\Helper;

use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Service\FeatureAccess;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class CategorySorting extends AbstractHelper
{
    const NOSTO_PERSONALIZED_KEY = 'nosto-personalized';

    const NOSTO_TOPLIST_KEY = 'nosto-toplist';

    /** @var NostoHelperScope */
    private $nostoHelperScope;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /**
     * CategorySorting constructor.
     * @param Account $nostoHelperAccount
     * @param Scope $nostoHelperScope
     * @param Context $context
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        Context $context
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperScope = $nostoHelperScope;
        parent::__construct($context);
    }

    /**
     * Return array that contains all sorting options offered by Nosto
     *
     * @return array
     */
    public static function getNostoSortingOptions()
    {
        return [
            self::NOSTO_PERSONALIZED_KEY => __('Personalized for you'),
            self::NOSTO_TOPLIST_KEY => __('Top products')
        ];
    }

    /**
     * Returns if any store has APPS token
     *
     * @param $id
     * @return bool
     */
    public function canUseCategorySorting($id)
    {
        $accounts = [];

        if ($id === 0) {
            $stores = $this->nostoHelperAccount->getStoresWithNosto();
            foreach ($stores as $store) {
                $account = $this->nostoHelperAccount->findAccount($store);
                if ($account !== null) {
                    $accounts[] = $account;
                }
            }
        } else {
            $store = $this->nostoHelperScope->getStore($id);
            $account = $this->nostoHelperAccount->findAccount($store);
            if ($account !== null) {
                $accounts[] = $account;
            }
        }

        foreach ($accounts as $account) {
            $featureAccess = new FeatureAccess($account);
            if ($featureAccess->canUseGraphql()) {
                return true;
            }
        }

        return false;
    }
}
