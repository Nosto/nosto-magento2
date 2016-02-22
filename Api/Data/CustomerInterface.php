<?php
namespace Nosto\Tagging\Api\Data;

/**
 * Created by PhpStorm.
 * User: hannupolonen
 * Date: 22/02/16
 * Time: 11:29
 */
interface CustomerInterface
{
    const CUSTOMER_ID   = 'customer_id';
    const QUOTE_ID      = 'quote_id';
    const NOSTO_ID      = 'nosto_id';
    const CREATED_AT    = 'created_at';
    const UPDATED_AT    = 'updated_at';

    /**
     * Get customer id
     * @return int|null
     */
    public function getCustomerId();

    /**
     * Get quote id
     * @return int|null
     */
    public function getQuoteId();

    /**
     * Get Nosto Id
     * @return string
     */
    public function getNostoId();

    /**
     * Get created at time 
     * @return \DateTime
     */
    public function getCreatedAt();

    /**
     * Get updated at time
     * @return \DateTime
     */
    public function getUpdatedAt();
    
    /**
     * Set customer id
     * @param int $customerId
     */
    public function setCustomerId($customerId);

    /**
     * Set quote id
     * @param int $quoteId
     */
    public function setQuoteId($quoteId);

    /**
     * Set Nosto Id
     * @param string $nostoId
     */
    public function setNostoId($nostoId);

    /**
     * Set created at time
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt);

    /**
     * Set updated at time
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt);

}