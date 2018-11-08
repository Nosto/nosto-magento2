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

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\Product;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Magento\Store\Model\Store;

/**
 * Price helper used for product price related tasks.
 */
class Ratings extends AbstractHelper
{


    protected $moduleManager;
    private $_config;
    private $_model;
    private $_helper;
    protected $_storeManager;


    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param CatalogHelper $catalogHelper the catalog helper.
     * @param RuleFactory $ruleFactory
     * @param TimezoneInterface $localeDate
     * @param NostoProductRepository $nostoProductRepository
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Module\Manager $moduleManager,
        \Yotpo\Yotpo\Block\Config $config,
        \Yotpo\Yotpo\Model\Richsnippet $model,
        \Yotpo\Yotpo\Helper\ApiClient $helper,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        NostoHelperData $nostoHelperData

    ) {
        parent::__construct($context);
        $this->moduleManager = $moduleManager;
        $this->_config = $config;
        $this->_model = $model;
        $this->_helper = $helper;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
    }


    public function getRatings(Product $product, Store $store){



        if ($this->moduleManager->isEnabled("Yotpo_Yotpo")){

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            try {
                /** @var \Yotpo\Yotpo\Helper\RichSnippets $YotpoHelper */
                $YotpoHelper = $objectManager->get('Yotpo\Yotpo\Helper\RichSnippets');
            } catch (\Exception $e) {
                // log exception here
            }

            $ratings = $YotpoHelper->getRichSnippet();
        }
        if ($this->nostoDataHelper->isRatingTaggingEnabled($store)) {

            return [
                "average_score" => $this->buildRatingValue($product, $store),
                "average_score" => $this->buildReviewCount($product, $store)
            ];
        }
        else {
            return null;
        }


        }



    /**
     * Helper method to fetch and return the normalised rating value for a product. The rating is
     * normalised to a 0-5 value.
     *
     * @param Product $product the product whose rating value to fetch
     * @param Store $store the store scope in which to fetch the rating
     * @return float|null the normalized rating value of the product
     */
    private function buildRatingValue(Product $product, Store $store)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$product->getRatingSummary()) {
            $this->reviewFactory->create()->getEntitySummary($product, $store->getId());
        }

        /** @noinspection PhpUndefinedMethodInspection */
        if ($product->getRatingSummary()->getReviewsCount() > 0) {
            /** @noinspection PhpUndefinedMethodInspection */
            return round($product->getRatingSummary()->getRatingSummary() / 20, 1);
        } else {
            return null;
        }
    }

    /**
     * Helper method to fetch and return the total review count for a product. The review counts are
     * returned as is.
     *
     * @param Product $product the product whose rating value to fetch
     * @param Store $store the store scope in which to fetch the rating
     * @return int|null the normalized rating value of the product
     */
    private function buildReviewCount(Product $product, Store $store)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$product->getRatingSummary()) {
            $this->reviewFactory->create()->getEntitySummary($product, $store->getId());
        }

        /** @noinspection PhpUndefinedMethodInspection */
        if ($product->getRatingSummary()->getReviewsCount() > 0) {
            /** @noinspection PhpUndefinedMethodInspection */
            return $product->getRatingSummary()->getReviewsCount();
        } else {
            return null;
        }
    }



}
