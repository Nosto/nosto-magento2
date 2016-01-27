<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Helper\Data;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Embed script block that includes the Nosto script in the page <head>.
 * This block should be included on all pages.
 */
class Embed extends Template
{
    /**
     * The default Nosto server address to use if none is configured.
     */
    const DEFAULT_SERVER_ADDRESS = 'connect.nosto.com';

    /**
     * @inheritdoc
     */
    protected $_template = 'embed.phtml';

    /**
     * Constructor.
     *
     * @param Context $context the context.
     * @param Account $accountHelper the account helper.
     * @param Data    $dataHelper the data helper.
     * @param array   $data optional data.
     */
    public function __construct(
        Context $context,
        Account $accountHelper,
        Data $dataHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->_accountHelper = $accountHelper;
        $this->_dataHelper = $dataHelper;
    }

    /**
     * Returns the account name for the current store.
     *
     * @return string the account name or empty string if account is not found.
     */
    public function getAccountName()
    {
        $store = $this->_storeManager->getStore(true);
        $account = $this->_accountHelper->findAccount($store);
        return !is_null($account) ? $account->getName() : '';
    }

    /**
     * Returns the Nosto server address.
     * This is taken from the local environment if it is set, or else it
     * defaults to "connect.nosto.com".
     *
     * @return string the url.
     */
    public function getServerAddress()
    {
        return isset($_ENV['NOSTO_SERVER_URL'])
            ? $_ENV['NOSTO_SERVER_URL']
            : self::DEFAULT_SERVER_ADDRESS;
    }
}
