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
use Nosto\Tagging\Logger\Logger as NostoLogger;
use Magento\Review\Model\ReviewFactory;
use Nosto\Tagging\Model\Product\Ratings as ProductRatings;

/**
 * Price helper used for product price related tasks.
 */
class Ratings extends AbstractHelper
{


    protected $moduleManager;
    protected $nostoDataHelper;
    private $logger;
    private $reviewFactory;

    //To be removed
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
        NostoHelperData $nostoHelperData,
        ReviewFactory $reviewFactory,
        NostoLogger $logger,


        //To be removed
        \Yotpo\Yotpo\Block\Config $config,
        \Yotpo\Yotpo\Model\Richsnippet $model,
        \Yotpo\Yotpo\Helper\ApiClient $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager

    ) {
        parent::__construct($context);
        $this->moduleManager = $moduleManager;
        $this->nostoDataHelper = $nostoHelperData;
        $this->logger = $logger;
        $this->reviewFactory = $reviewFactory;

        $this->_config = $config;
        $this->_model = $model;
        $this->_helper = $helper;
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
    }

    /**
     * Get ratings
     *
     * @param Product $product
     * @param Store $store
     * @return ProductRatings|null
     */
    public function getRatings(Product $product, Store $store){
        $ratings = $this->getRatingsFromProviders($product, $store);

        if($ratings == null){
            return null;
        }

        $productRatings = new ProductRatings($ratings["reviews_count"], $ratings["average_score"]);
        return $productRatings;

    }

    /**
     * Get Ratings of product from different providers
     *
     * @param Product $product
     * @param Store $store
     * @return array|null
     */
    private function getRatingsFromProviders(Product $product, Store $store){

//        $this->getRichSnippet();
//        return;

        if($this->nostoDataHelper->isRatingTaggingEnabled($store)){

            $provider = $this->nostoDataHelper->getRatingTaggingProvider($store);

            if($provider === NostoHelperData::SETTING_VALUE_YOTPO_RATINGS) {

                if (!$this->moduleManager->isEnabled("Yotpo_Yotpo")){
                    return null;
                }

                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                try {
                    /** @var \Yotpo\Yotpo\Helper\RichSnippets $YotpoHelper */
                    $YotpoHelper = $objectManager->get('Yotpo\Yotpo\Helper\RichSnippets');
                } catch (\Exception $e) {
                    $this->logger->exception($e);
                }

                $ratings = $YotpoHelper->getRichSnippet();

                if($ratings == ""){
                    return null;
                }

                return [
                    "average_score" => $ratings["average_score"],
                    "reviews_count" => $ratings["reviews_count"]
                ];

            }

            else if( $provider === NostoHelperData::SETTING_VALUE_MAGENTO_RATINGS){
                return [
                    "average_score" => $this->buildRatingValue($product, $store),
                    "reviews_count" => $this->buildReviewCount($product, $store)
                ];
            }

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




    //To be removed
    public function getRichSnippet() {
        try {

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $product = $objectManager->get('Magento\Framework\Registry')->registry('current_product');//get current product
            $productId = $product->getId();
            $storeId = $this->_storeManager->getStore()->getId();

            $snippet = $this->_model->getSnippetByProductIdAndStoreId($productId, $storeId);


            if (($snippet == null) || (!$snippet->isValid())) {
                //no snippet for product or snippet isn't valid anymore. get valid snippet code from yotpo api
                $res = $this->_helper->createApiGet("products/" . ($this->_config->getAppKey()) . "/richsnippet/" . $productId, 2);

                if ($res["code"] != 200) {
                    //product not found or feature disabled.
                    return "";
                }
                $body = $res["body"];
                $averageScore = $body->response->rich_snippet->reviews_average;
                $reviewsCount = $body->response->rich_snippet->reviews_count;
                $ttl = $body->response->rich_snippet->ttl;
                if ($snippet == null) {
                    $snippet = $this->_model;
                    $snippet->setProductId($productId);
                    $snippet->setStoreId($storeId);
                }
                $snippet->setAverageScore($averageScore);
                $snippet->setReviewsCount($reviewsCount);
                $snippet->setExpirationTime(date('Y-m-d H:i:s', time() + $ttl));
                $snippet->save();
                return array("average_score" => $averageScore, "reviews_count" => $reviewsCount);
            }
            return array("average_score" => $snippet->getAverageScore(), "reviews_count" => $snippet->getReviewsCount());
        } catch (\Exception $e) {
            $this->_logger->addDebug('error: ' . $e);
        }
        return array();
        return true;

    }

}
