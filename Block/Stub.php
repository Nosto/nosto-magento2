<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2019 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Helper\Variation as NostoHelperVariation;
use Nosto\Tagging\Helper\Customer as NostoHelperCustomer;

/**
 * Nosto JS stub block
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Stub extends Template
{
    use TaggingTrait {
        TaggingTrait::__construct as taggingConstruct; // @codingStandardsIgnoreLine
    }

    /**
     * @var NostoHelperData
     */
    private $nostoHelperData;

    /**
     * @var NostoHelperCustomer
     */
    private $nostoHelperCustomer;

    /**
     * @var NostoHelperVariation
     */
    private $nostoHelperVariation;

    /**
     * Stub constructor.
     * @param Template\Context $context
     * @param NostoHelperAccount $nostoHelperAccount
     * @param NostoHelperScope $nostoHelperScope
     * @param NostoHelperData $nostoHelperData
     * @param NostoHelperCustomer $nostoHelperCustomer
     * @param NostoHelperVariation $nostoHelperVariation
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        NostoHelperAccount $nostoHelperAccount,
        NostoHelperScope $nostoHelperScope,
        NostoHelperData $nostoHelperData,
        NostoHelperCustomer $nostoHelperCustomer,
        NostoHelperVariation $nostoHelperVariation,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->taggingConstruct($nostoHelperAccount, $nostoHelperScope);
        $this->nostoHelperData = $nostoHelperData;
        $this->nostoHelperCustomer = $nostoHelperCustomer;
        $this->nostoHelperVariation = $nostoHelperVariation;
    }

    /**
     *
     * @return null
     */
    public function getAbstractObject()
    {
        return null;
    }

    /**
     * Returns if autoloading recommendations is disabled or not.
     *
     * @return boolean
     */
    public function isRecoAutoloadDisabled()
    {
        $store = $this->getNostoHelperScope()->getStore(true);
        // If price variations are used and the variation something else than
        // the default one we disable the autoload. For default variation
        // the sections are not loaded and loadRecommendations() is not called
        if ($this->nostoHelperData->isPricingVariationEnabled($store)
            && !$this->nostoHelperVariation->isDefaultVariationCode(
                $this->nostoHelperCustomer->getGroupCode()
            )
        ) {
            return true;
        }
        return false;
    }
}
