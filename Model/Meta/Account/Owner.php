<?php

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
