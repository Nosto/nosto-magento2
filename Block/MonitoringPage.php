<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Indexer\Model\ResourceModel\Indexer\State\Collection;
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
        Scope $scope,
        Data $settings,
        array $data = [],
    ) {
        parent::__construct($context, $data);
        $this->account = $account;
        $this->store = $scope->getStore();
        $this->settings = $settings;
    }

    public function getFormAction(string $url): string
    {
        return $this->getUrl($url, ['_secure' => true]);
    }

    public function checkIfNostoInstalledAndEnabled(): string
    {
        if (true === $this->account->nostoInstalledAndEnabled($this->store)) {
            return 'Yes';
        }

        return 'No';
    }

    public function getAccountNameAndTokens(): NostoAccount
    {
        return $this->account->findAccount($this->store);
    }

    public function getStoresWithNosto(): array
    {
        return $this->account->getStoresWithNosto();
    }

    public function getSettingsValue(string $method): string
    {
        if (true === $this->settings->$method($this->store) || '1' === $this->settings->$method($this->store)) {
            return 'Yes';
        }

        return 'No';
    }

    public function getStringSettingsValues(string $method): string
    {
        return $this->settings->$method($this->store);
    }

    public function getLastIndexedTime(string $indexerName): array
    {
        $indexerCollection = ObjectManager::getInstance()
            ->create(Collection::class);
        $nostoIndexer = $indexerCollection->getItemByColumnValue('indexer_id', $indexerName);

        return [
            'indexer_id' => $nostoIndexer->getData('indexer_id'),
            'updated' => $nostoIndexer->getData('updated')
        ];
    }
}
