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

namespace Nosto\Tagging\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ResourceConnection\SourceProviderInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Data\SearchResultInterface;
use Nosto\NostoException;

/**
 * Class AbstractBaseRepository
 *
 * Note - if M2 Factories some day implement an interface we can move
 * the injected factories into the constructor
 *
 * @package Nosto\Tagging\Model
 */
abstract class AbstractBaseRepository
{

    private $objectResource;
    private $objectCollectionFactory;
    private $objectSearchResultsFactory;

    /**
     * AbstractBaseRepository constructor.
     * @param AbstractDb $objectResource
     */
    protected function __construct(
        AbstractDb $objectResource
    )
    {
        $this->objectResource = $objectResource;
    }

    /**
     * @return AbstractDb
     */
    public function getObjectResource()
    {
        return $this->objectResource;
    }

    /**
     * @return object (Factory)
     */
    public function getObjectCollectionFactory()
    {
        return $this->objectCollectionFactory;
    }

    /**
     * @param object $objectCollectionFactory Factory object
     *
     * @throws NostoException in case of invalid argument
     */
    public function setObjectCollectionFactory($objectCollectionFactory)
    {
        if (!$this->isFactory($objectCollectionFactory)) {
            throw new NostoException('Invalid argument in setObjectCollectionFactory, expected Factory');
        }
        $this->objectCollectionFactory = $objectCollectionFactory;
    }

    /**
     * @return mixed
     */
    public function getObjectSearchResultsFactory()
    {
        return $this->objectSearchResultsFactory;
    }

    /**
     *
     * @param object $objectSearchResultsFactory Factory object
     *
     * @throws NostoException
     */
    public function setObjectSearchResultsFactory($objectSearchResultsFactory)
    {
        if (!$this->isFactory($objectSearchResultsFactory)) {
            throw new NostoException('Invalid argument in setObjectSearchResultsFactory, expected Factory');
        }
        $this->objectSearchResultsFactory = $objectSearchResultsFactory;
    }


    /**
     * @inheritdoc
     */
    public function search(SearchCriteriaInterface $searchCriteria)
    {
        /* @var AbstractCollection $collection */
        $collection = $this->objectCollectionFactory->create();
        $this->addFiltersToCollection($searchCriteria, $collection);
        $collection->load();
        /* @var SearchResultInterface $searchResults */
        $searchResults = $this->objectSearchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    private function addFiltersToCollection(SearchCriteriaInterface $searchCriteria, SourceProviderInterface $collection)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            $fields = $conditions = [];
            foreach ($filterGroup->getFilters() as $filter) {
                $fields[] = $filter->getField();
                $conditions[] = [$filter->getConditionType() => $filter->getValue()];
            }
            $collection->addFieldToFilter($fields, $conditions);
        }
    }

    /**
     * Validates that given parameter / object is a factory
     *
     * @param mixed $param
     *
     * @return bool
     */
    private function isFactory($param)
    {
        if(is_object($param) && method_exists($param, 'create')) {

            return true;
        }

        return false;
    }
}