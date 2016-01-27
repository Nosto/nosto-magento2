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

class Sso implements \NostoAccountMetaSingleSignOnInterface
{
    /**
     * @var string the name of the platform.
     */
    protected $_platform = 'magento'; // todo: change to "magento2"

    /**
     * @var string the admin user first name.
     */
    protected $_firstName;

    /**
     * @var string the admin user last name.
     */
    protected $_lastName;

    /**
     * @var string the admin user email address.
     */
    protected $_email;

    /**
     * The name of the platform.
     * A list of valid platform names is issued by Nosto.
     *
     * @return string the platform name.
     */
    public function getPlatform()
    {
        return $this->_platform;
    }

    /**
     * The first name of the user who is doing the SSO.
     *
     * @return string the first name.
     */
    public function getFirstName()
    {
        return $this->_firstName;
    }

    public function setFirstName($firstName)
    {
        $this->_firstName = $firstName;
    }

    /**
     * The last name of the user who is doing the SSO.
     *
     * @return string the last name.
     */
    public function getLastName()
    {
        return $this->_lastName;
    }

    // todo

    public function setLastName($lastName)
    {
        $this->_lastName = $lastName;
    }

    /**
     * The email address of the user who doing the SSO.
     *
     * @return string the email address.
     */
    public function getEmail()
    {
        return $this->_email;
    }

    public function setEmail($email)
    {
        $this->_email = $email;
    }
}
