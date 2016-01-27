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

namespace Nosto\Tagging\Model\Meta\Account;

class Owner implements \NostoAccountMetaOwnerInterface
{
    /**
     * @var string the account owner first name.
     */
    protected $_firstName;

    /**
     * @var string the account owner last name.
     */
    protected $_lastName;

    /**
     * @var string the account owner email address.
     */
    protected $_email;

    /**
     * The first name of the account owner.
     *
     * @return string the first name.
     */
    public function getFirstName()
    {
        return $this->_firstName;
    }

    /**
     * The last name of the account owner.
     *
     * @return string the last name.
     */
    public function getLastName()
    {
        return $this->_lastName;
    }

    /**
     * The email address of the account owner.
     *
     * @return string the email address.
     */
    public function getEmail()
    {
        return $this->_email;
    }

    // todo

    public function setFirstName($firstName)
    {
        $this->_firstName = $firstName;
    }

    public function setLastName($lastName)
    {
        $this->_lastName = $lastName;
    }

    public function setEmail($email)
    {
        $this->_email = $email;
    }
}
