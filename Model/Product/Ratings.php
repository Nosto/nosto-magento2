<?php
/**
 * Created by PhpStorm.
 * User: olsiqose
 * Date: 09/11/2018
 * Time: 11.54
 */

namespace Nosto\Tagging\Model\Product;


class Ratings
{
    /** @var int */
    protected $reviewCount;
    /** @var float */
    protected $rating;

    /**
     * @inheritdoc
     */
    public function __construct($reviewCount, $rating )
    {
        $this->setRating($rating);
        $this->setReviewCount($reviewCount);
    }


    public function getReviewCount()
    {
        return $this->reviewCount;
    }

    /**
     * @param int $reviewCount
     */
    public function setReviewCount($reviewCount)
    {
        $this->reviewCount = (int)$reviewCount;
    }
    /**
     * @inheritdoc
     */
    public function getRating()
    {
        return $this->rating;
    }
    /**
     * @param float $rating
     */
    public function setRating($rating)
    {
        $this->rating = (float)$rating;
    }

}