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
    /** @var CustomerResource $customerResource */
    private CustomerResource $customerResource;

    /** @var Logger $logger */
    private Logger $logger;

    /** @var CustomerCollection $collection */
    private CustomerCollection $collection;

    /**
     * NostoPopulateCustomerReferenceCommand Constructor
     *
     * @param CustomerResource $customerResource
     * @param Logger $logger
     * @param CustomerCollection $collection
     * @param string|null $name
     */
    public function __construct(
        CustomerResource $customerResource,
        Logger $logger,
        CustomerCollection $collection,
        ?string $name = null
    ) {
        $this->customerResource = $customerResource;
        $this->logger = $logger;
        $this->collection = $collection;

        parent::__construct($name);
    }

    /**
     * Configure the command and the arguments
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('nosto:generate:customer-reference')
            ->setDescription('Generate automatically customer_reference for all customer missing it');

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

        return 0;
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
                    $this->logger->error(sprintf(
                        'Customer update failed for ID %s, Error: %s',
                        $customer->getId(),
                        $e->getMessage()
                    ));
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
     * @param $customer
     * @return void
     */
    private function generateCustomerReference($customer): void
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
