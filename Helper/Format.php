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
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Nosto\Tagging\Helper;

/** @noinspection PhpIncludeInspection */
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use NostoHelperDate;
use NostoHelperPrice;

/**
 * NostoHelperFormat helper used for common formatting tasks.
 */
class Format extends AbstractHelper
{
    /**
     * @var NostoHelperPrice the nosto price formatter.
     */
    protected $priceFormatter;

    /**
     * @var NostoHelperDate the nosto date formatter.
     */
    protected $dateFormatter;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param NostoHelperPrice $priceFormatter the nosto price formatter.
     * @param NostoHelperDate $dateFormatter the nosto date formatter.
     */
    public function __construct(
        Context $context,
        NostoHelperPrice $priceFormatter,
        NostoHelperDate $dateFormatter
    ) {
        parent::__construct($context);

        $this->priceFormatter = $priceFormatter;
        $this->dateFormatter = $dateFormatter;
    }

    /**
     * Formats price into Nosto format, e.g. 1000.99.
     *
     * @see NostoHelperPrice::format()
     * @param int|float|string $price the price string to format.
     * @return string the formatted price.
     */
    public function formatPrice($price)
    {
        return $this->priceFormatter->format($price);
    }

    /**
     * Formats date into Nosto format, i.e. Y-m-d.
     *
     * @see NostoHelperDate::format()
     * @param string $date the date string to format.
     * @return string the formatted date.
     */
    public function formatDate($date)
    {
        return $this->dateFormatter->format($date);
    }
}
