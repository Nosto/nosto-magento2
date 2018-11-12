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
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager;

/**
 * Price helper used for product price related tasks.
 */
class Ratings extends AbstractHelper
{
    const REVIEW_COUNT= 'reviews_count';
    const AVERAGE_SCORE = 'average_score';

    private $moduleManager;
    private $nostoDataHelper;
    private $logger;
    private $reviewFactory;
    private $ratingsFactory;

    /**
     * Ratings constructor.
     * @param Context $context
     * @param Manager $moduleManager
     * @param Data $nostoHelperData
     * @param ReviewFactory $reviewFactory
     * @param NostoLogger $logger
     * @param RatingsFactory $ratingsFactory
     */
    public function __construct(
        Context $context,
        Manager $moduleManager,
        NostoHelperData $nostoHelperData,
        ReviewFactory $reviewFactory,
        NostoLogger $logger,
        RatingsFactory $ratingsFactory
    ) {
        parent::__construct($context);
        $this->moduleManager = $moduleManager;
        $this->nostoDataHelper = $nostoHelperData;
        $this->logger = $logger;
        $this->reviewFactory = $reviewFactory;
        $this->ratingsFactory = $ratingsFactory;
    }

    /**
     * Get ratings
     *
     * @param Product $product
     * @param Store $store
     * @return ProductRatings|null
     */
    public function getRatings(Product $product, Store $store)
    {
        $ratings = $this->getRatingsFromProviders($product, $store);
        if ($ratings === null) {
            return null;
        }

        $productRatings = new ProductRatings();
        $productRatings->setReviewCount($ratings[self::REVIEW_COUNT]);
        $productRatings->setRating($ratings[self::AVERAGE_SCORE]);
        return $productRatings;
    }

    /**
     * Get Ratings of product from different providers
     *
     * @param Product $product
     * @param Store $store
     * @return array|null
     */
    private function getRatingsFromProviders(Product $product, Store $store)
    {

        if ($this->nostoDataHelper->isRatingTaggingEnabled($store)) {
            $provider = $this->nostoDataHelper->getRatingTaggingProvider($store);

            if ($provider === NostoHelperData::SETTING_VALUE_YOTPO_RATINGS) {
                if (!$this->moduleManager->isEnabled("Yotpo_Yotpo")) {
                    return null;
                }

                try {
                    $ratings = $this->ratingsFactory->create()->getRichSnippet();
                } catch (\Exception $e) {
                    $this->logger->exception($e);
                }

                if ($ratings == "") {
                    return null;
                }

                return [
                    self::AVERAGE_SCORE => $ratings[self::AVERAGE_SCORE],
                    self::REVIEW_COUNT => $ratings[self::REVIEW_COUNT]
                ];
            } elseif ($provider === NostoHelperData::SETTING_VALUE_MAGENTO_RATINGS) {
                return [
                    self::AVERAGE_SCORE => $this->buildRatingValue($product, $store),
                    self::REVIEW_COUNT => $this->buildReviewCount($product, $store)
                ];
            }
        } else {
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
