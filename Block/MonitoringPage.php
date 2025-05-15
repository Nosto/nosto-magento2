<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\Store;
use Nosto\Model\Signup\Account as NostoAccount;
use Nosto\Tagging\Helper\Account;
use Nosto\Tagging\Helper\Data;
use Nosto\Tagging\Helper\Scope;

class MonitoringPage extends Template
{
    /** @var Account $account */
    private Account $account;

    /** @var Store $store */
    private Store $store;

    /** @var Data $settings */
    private Data $settings;

    public function __construct(
        Context $context,
        Account $account,
        Scope $store,
        Data $settings,
        array $data = [],
    ) {
        parent::__construct($context, $data);
        $this->account = $account;
        $this->store = $store->getStore();
        $this->settings = $settings;
    }

    public function getLogoutFormAction(): string
    {
        return $this->getUrl('nosto/monitoring/logout', ['_secure' => true]);
    }

    public function getDownloadLogFilesFormAction(): string
    {
        return $this->getUrl('nosto/monitoring/logs', ['_secure' => true]);
    }

    public function checkIfNostoInstalledAndEnabled(): bool
    {
        return $this->account->nostoInstalledAndEnabled($this->store);
    }

    public function getAccountNameAndTokens(): NostoAccount
    {
        return $this->account->findAccount($this->store);
    }

    public function getStoresWithNosto(): array
    {
        return $this->account->getStoresWithNosto();
    }

    public function getProductImageVersion(): ?string
    {
        return $this->settings->getProductImageVersion($this->store);
    }

    public function getRemovePubDirectoryFromProductImageUrl(): ?bool
    {
        return $this->settings->getRemovePubDirectoryFromProductImageUrl($this->store);
    }

    public function getBrandAttribute(): ?string
    {
        return $this->settings->getBrandAttribute($this->store);
    }

    public function getMarginAttribute(): ?string
    {
        return $this->settings->getMarginAttribute($this->store);
    }

    public function getGtinAttribute(): ?string
    {
        return $this->settings->getGtinAttribute($this->store);
    }
}
