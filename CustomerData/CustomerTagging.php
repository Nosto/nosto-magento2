<?php
/**
 * Created by PhpStorm.
 * User: hannupolonen
 * Date: 16/12/16
 * Time: 14:39
 */
namespace Nosto\Tagging\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;

class CustomerTagging implements SectionSourceInterface
{
    /**
     */
    public function __construct() {}

    /**
     * @inheritdoc
     */
    public function getSectionData()
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Nullero',
            'email' => 'nullero@nosto.com'
        ];
    }
}
