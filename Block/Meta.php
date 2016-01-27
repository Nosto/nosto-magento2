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

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Nosto\Tagging\Helper\Data as DataHelper;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Meta data block for outputting <meta> elements in the page <head>.
 * This block should be included on all pages.
 */
class Meta extends Template
{
    /**
     * @inheritdoc
     */
    protected $_template = 'meta.phtml';

    /**
     * @var DataHelper the module data helper.
     */
    protected $_dataHelper;

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param DataHelper $dataHelper the data helper.
     * @param array $data optional data.
     */
    public function __construct(
        Context $context,
        DataHelper $dataHelper,
        array $data = []
    )
    {
        parent::__construct($context, $data);

        $this->_dataHelper = $dataHelper;
    }

    /**
     * Returns the module version number.
     *
     * @return string the module version number.
     */
    public function getModuleVersion()
    {
        // todo
        return 'todo';
    }

    /**
     * Returns the unique installation ID.
     *
     * @return string the unique ID.
     */
    public function getInstallationId()
    {
        return $this->_dataHelper->getInstallationId();
    }

    /**
     * Returns the current stores language code in ISO 639-1 format.
     *
     * @return string the language code.
     */
    public function getLanguageCode()
    {
        /** @var Store $store */
        $store = $this->_storeManager->getStore(true);
        return substr($store->getConfig('general/locale/code'), 0, 2);
    }
}
