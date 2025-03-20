<?php

namespace Nosto\Tagging\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Nosto\Tagging\Util\PopulateCustomerReference;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NostoPopulateCustomerReferenceCommand extends Command
{
    private ObjectManagerInterface $objectManager;
    private State $appState;

    public function __construct(ObjectManagerInterface $objectManager, State $appState)
    {
        parent::__construct();
        $this->objectManager = $objectManager;
        $this->appState = $appState;
    }

    public function configure()
    {
        $this->setName('nosto:populate:customer:reference')
            ->setDescription('Populate customer reference');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('adminhtml');

            /** @var PopulateCustomerReference $populateCustomerReference */
            $populateCustomerReference = $this->objectManager->create(PopulateCustomerReference::class);
            $populateCustomerReference->apply();

            $output->writeln('<info>PopulateCustomerReference done!</info>');
            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return 1;
        } catch (\Exception $e) {
            $output->writeln('<error>Unexpected error: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
