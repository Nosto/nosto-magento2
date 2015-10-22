<?php

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

    /**
     * The last name of the user who is doing the SSO.
     *
     * @return string the last name.
     */
    public function getLastName()
    {
        return $this->_lastName;
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
