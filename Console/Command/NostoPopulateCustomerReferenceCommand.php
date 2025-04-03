<?php

declare(strict_types=1);

namespace Nosto\Tagging\Console\Command;

use Exception;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Customer\Collection as CustomerCollection;
use Nosto\NostoException;
use Nosto\Tagging\Util\Customer as CustomerUtil;
use Nosto\Tagging\Util\PagingIterator;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Logger\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NostoPopulateCustomerReferenceCommand extends Command
{
    /**
     * NostoPopulateCustomerReferenceCommand Constructor
     *
     * @param CustomerResource $customerResource
     * @param Logger $logger
     * @param CustomerCollection $collection
     * @param string|null $name
     */
    public function __construct(
        private readonly CustomerResource $customerResource,
        private readonly Logger $logger,
        private readonly CustomerCollection $collection,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    /**
     * Configure the command and the arguments
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('nosto:populate:customer:reference')
            ->setDescription('Generate new customer reference');

        parent::configure();
    }

    /**
     * Executes the current command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws NostoException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->generateAndSaveCustomerReference();
        $io->success('Customer references successfully generated.');

        return Command::SUCCESS;
    }

    /**
     * Generate and save customer reference attribute
     *
     * @return void
     * @throws NostoException
     */
    private function generateAndSaveCustomerReference(): void
    {
        $iterator = $this->getCustomersWithoutCustomerReference();
        foreach ($iterator as $page) {
            /* @var Customer $customer */
            foreach ($page as $customer) {
                $this->generateCustomerReference($customer);
                try {
                    $this->saveCustomerReference($customer);
                } catch (Exception $e) {
                    $this->logger->exception($e);
                }
            }
        }
    }

    /**
     * Returns only customers without customer reference attribute
     * Fetch 1000 customers per iteration
     *
     * @return PagingIterator
     * @throws NostoException
     */
    private function getCustomersWithoutCustomerReference(): PagingIterator
    {
        $customers = $this->collection
            ->addFieldToFilter(
                NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
                ['null' => true]
            )
            ->setPageSize(1000);

        return new PagingIterator($customers);
    }

    /**
     * Generate customer reference attribute
     *
     * @param Customer $customer
     * @return void
     */
    private function generateCustomerReference(Customer $customer): void
    {
        $customer->setData(
            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME,
            (new CustomerUtil)->generateCustomerReference($customer)
        );
    }

    /**
     * Save customer reference attribute to database
     *
     * @param Customer $customer
     * @return void
     * @throws Exception
     */
    private function saveCustomerReference(Customer $customer): void
    {
        $this->customerResource->saveAttribute(
            $customer,
            NostoHelperData::NOSTO_CUSTOMER_REFERENCE_ATTRIBUTE_NAME
        );
    }
}
