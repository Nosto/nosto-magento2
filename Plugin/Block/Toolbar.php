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

namespace Nosto\Tagging\Plugin\Block;

use Nosto\Tagging\Plugin\Catalog\Model\Config;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Block\Product\ProductList\Toolbar as MagentoToolbar;
use Magento\Store\Model\Store;
use Magento\Framework\Registry;
use Nosto\Tagging\Model\Category\Builder as CategoryBuilder;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nosto\Tagging\Model\Customer\Customer as NostoCustomer;
use Nosto\Tagging\Model\Service\Recommendation\Category as CategoryRecommendation;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Data\Collection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Nosto\Tagging\Logger\Logger as NostoLogger;

class Toolbar extends Template
{

    /**  @var StoreManagerInterface */
    private $storeManager;

    /** @var NostoHelperData */
    private $nostoHelperData;

    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /**  @var CategoryBuilder */
    private $categoryBuilder;

    /** @var Registry */
    private $registry;

    /** @var CookieManagerInterface */
    private $cookieManager;

    /** @var CategoryRecommendation */
    private $categoryRecommendation;

    /** @var NostoLogger */
    private $logger;

    /** @var AbstractCollection */
    protected $_collection = null;

    /**
     * Toolbar constructor.
     * @param Context $context
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperAccount $nostoHelperAccount
     * @param StoreManagerInterface $storeManager
     * @param CategoryBuilder $builder
     * @param CategoryRecommendation $categoryRecommendation
     * @param CookieManagerInterface $cookieManager
     * @param NostoLogger $logger
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        NostoHelperData $nostoHelperData,
        NostoHelperAccount $nostoHelperAccount,
        StoreManagerInterface $storeManager,
        CategoryBuilder $builder,
        CategoryRecommendation $categoryRecommendation,
        CookieManagerInterface $cookieManager,
        NostoLogger $logger,
        Registry $registry,
        array $data = []
    ) {
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->categoryBuilder = $builder;
        $this->storeManager = $storeManager;
        $this->cookieManager = $cookieManager;
        $this->categoryRecommendation = $categoryRecommendation;
        $this->logger = $logger;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Plugin - Used to modify default Sort By filters
     *
     * @param MagentoToolbar $subject
     * @param Collection $collection
     * @return $this
     * @throws NoSuchEntityException
     */
    public function afterSetCollection(
        MagentoToolbar $subject,
        $collection
    ) {
        $this->_collection = $collection;
        $store = $this->storeManager->getStore();
        $currentOrder = $subject->getCurrentOrder();
        if (($currentOrder === Config::NOSTO_PERSONALIZED_KEY
            || $currentOrder === Config::NOSTO_TOPLIST_KEY)
            && $this->nostoHelperAccount->nostoInstalledAndEnabled($store)
            && $this->nostoHelperData->isCategorySortingEnabled($store)
        ) {
            $limit = (int)$subject->getLimit();
            if ($limit) {
                $this->_collection->setPageSize($limit);
            }
            $currentPage = $subject->getCurrentPage();
            $this->_collection->setCurPage($currentPage);

            //Get ids of products to order
            $orderIds = $this->getSortedIds($store, $currentOrder);
            if (!empty($orderIds)) {
                try {
                    $zendExpression = new \Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $orderIds) . ') DESC');
                    $this->_collection->getSelect()->order($zendExpression);
                } catch (\Exception $e) {
                    $this->logger->exception($e);
                }
            }

            return $this;
        }
    }

    /**
     * @param Store $store
     * @param $type
     * @return array
     */
    private function getSortedIds(Store $store, $type)
    {
        $nostoAccount = $this->nostoHelperAccount->findAccount($store);
        $category = $this->registry->registry('current_category');
        $category = $this->categoryBuilder->build($category, $store);
        $nostoCustomer = $this->cookieManager->getCookie(NostoCustomer::COOKIE_NAME);
        return $this->categoryRecommendation->getSortedProductIds(
            $nostoAccount,
            $nostoCustomer,
            $category,
            $type
        );
    }
}