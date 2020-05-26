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

namespace Nosto\Tagging\Setup;

use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Store\Model\ScopeInterface;
use Nosto\NostoException;
use Nosto\Model\Product\Product;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Url as NostoHelperUrl;
use Nosto\Tagging\Logger\Logger;
use Nosto\Tagging\Model\Product\Cache;
use Nosto\Tagging\Model\ResourceModel\Product\Cache as CacheResource;
use Nosto\Tagging\Model\ResourceModel\Product\Cache\CacheCollectionFactory;
use Nosto\Tagging\Util\PagingIterator;
use Zend_Validate_Exception;

class UpgradeData extends CoreData implements UpgradeDataInterface
{
    /** @var NostoHelperAccount */
    private $nostoHelperAccount;

    /** @var NostoHelperUrl */
    private $nostoHelperUrl;

    /** @var WriterInterface */
    private $config;

    /** @var CacheCollectionFactory */
    private $cacheCollectionFactory;

    /**
     * UpgradeData constructor.
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperUrl $nostoHelperUrl
     * @param WriterInterface $appConfig
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param CustomerResource $customerResource
     * @param CacheCollectionFactory $cacheCollectionFactory
     * @param Logger $logger
     */
    public function __construct(
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperUrl $nostoHelperUrl,
        WriterInterface $appConfig,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        CustomerCollectionFactory $customerCollectionFactory,
        CustomerResource $customerResource,
        CacheCollectionFactory $cacheCollectionFactory,
        Logger $logger
    ) {
        $this->nostoHelperAccount = $nostoHelperAccount;
        $this->nostoHelperUrl = $nostoHelperUrl;
        $this->config = $appConfig;
        $this->cacheCollectionFactory = $cacheCollectionFactory;
        parent::__construct(
            $customerSetupFactory,
            $attributeSetFactory,
            $customerCollectionFactory,
            $customerResource,
            $logger
        );
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @throws LocalizedException
     * @throws Zend_Validate_Exception
     * @throws NostoException
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context) // @codingStandardsIgnoreLine
    {
        $fromVersion = $context->getVersion();
        if (version_compare($fromVersion, '3.1.0', '<=')) {
            $this->insertStoreDomain();
        }
        if (version_compare($fromVersion, '3.6.0', '<=')) {
            $this->addCustomerReference($setup);
        }
        if (version_compare($fromVersion, '3.10.4', '<=')) {
            $this->alterCustomerReferenceNonEditable($setup);
        }
        if (version_compare($fromVersion, '3.10.5', '<=')) {
            $this->populateCustomerReference();
        }
        if (version_compare($fromVersion, '4.0.3', '<=')) {
            $this->convertProductDataToBase64($setup->getConnection());
        }
    }

    /**
     * Insert store domain when missing in database
     */
    private function insertStoreDomain()
    {
        $stores = $this->nostoHelperAccount->getStoresWithNosto();
        foreach ($stores as $store) {
            $storeFrontDomain = $this->nostoHelperAccount->getStoreFrontDomain($store);
            if ($storeFrontDomain === null ||
                $storeFrontDomain === ''
            ) {
                // @codingStandardsIgnoreLine
                $this->config->save(
                    NostoHelperAccount::XML_PATH_DOMAIN,
                    $this->nostoHelperUrl->getActiveDomain($store),
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }
        }
    }

    /**
     * Converts serialized product data to base64 encoded data to avoid charset & collation problems
     *
     * @param AdapterInterface $connection
     */
    public function convertProductDataToBase64(AdapterInterface $connection)
    {
        try {
            $cachedProductCollection = $this->cacheCollectionFactory->create()->setPageSize(1000);
            $iterator = new PagingIterator($cachedProductCollection);
            foreach ($iterator as $page) {
                $canBeConverted = [];
                $nullableIds = [];
                /* @var Cache $cachedProduct */
                foreach ($page as $cachedProduct) {
                    try {
                        unserialize($cachedProduct->getProductData(), [Product::class]); // @codingStandardsIgnoreLine
                        $canBeConverted[] = $cachedProduct->getId();
                    } catch (\Exception $e) {
                        $nullableIds[] = $cachedProduct->getId();
                    }
                }
                $this->base64EncodeByIds($connection, $canBeConverted);
                $this->nullifyProductDataByIds($connection, $nullableIds);
            }
        } catch (\Exception $e) {
            $this->getLogger()->exception($e);
        }
    }

    /**
     * Converts product data to base64 encdoded string for the given entity ids
     *
     * @param AdapterInterface $connection
     * @param array $ids
     */
    private function base64EncodeByIds(AdapterInterface $connection, array $ids)
    {
        if (!empty($ids)) {
            $convertSql = sprintf(
                'UPDATE %s SET %s = TO_BASE64(%s) WHERE %s IN(%s)',
                CacheResource::TABLE_NAME,
                Cache::PRODUCT_DATA,
                Cache::PRODUCT_DATA,
                Cache::ID,
                implode(',', $ids)
            );
            $connection->query($convertSql); // @codingStandardsIgnoreLine
        }
    }

    /**
     * Sets product data to NULL & marks the cached products are dirty for the given entity ids
     *
     * @param AdapterInterface $connection
     * @param array $ids
     */
    private function nullifyProductDataByIds(AdapterInterface $connection, array $ids)
    {
        if (!empty($ids)) {
            $setNullSql = sprintf(
                'UPDATE %s SET %s = NULL, %s=1 WHERE %s IN(%s)',
                CacheResource::TABLE_NAME,
                Cache::PRODUCT_DATA,
                Cache::IS_DIRTY,
                Cache::ID,
                implode(',', $ids)
            );
            $connection->query($setNullSql); // @codingStandardsIgnoreLine
        }
    }
}
