<?php

namespace Nosto\Tagging\Console\Command;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Nosto\Tagging\Helper\Data as NostoHelperData;
use Nosto\Tagging\Util\Customer as CustomerUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Magento\Framework\App\Bootstrap;

class GenerateCustomerCommand extends Command
{
    const NAME_ARGUMENT = 'count';

    /**
     * NostoGenerateCustomerReferenceCommand constructor.
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('customer:import')
            ->setDescription('Import customers into Magento.')
            ->addArgument(self::NAME_ARGUMENT, InputArgument::OPTIONAL, 'Number of customers to add', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $count = (int) $input->getArgument(self::NAME_ARGUMENT);
            $output->writeln("Adding $count customers...");

            require '/var/www/html/app/bootstrap.php';
            $bootstrap = Bootstrap::create(BP, $_SERVER);
            $objectManager = $bootstrap->getObjectManager();

            $customerFactory = $objectManager->get(CustomerInterfaceFactory::class);
            $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
            $encryptor = $objectManager->get(\Magento\Framework\Encryption\EncryptorInterface::class);
            $customerCollectionFactory = $objectManager->get(CollectionFactory::class);
            $customerCollection = $customerCollectionFactory->create();
            $customerCount = $customerCollection->getSize() + 1;

            for ($i = $customerCount; $i <= $count + $customerCount; $i++) {
                $customer = $customerFactory->create();
                $customer->setFirstname('John ' . $i)
                    ->setLastname('Doe ' . $i)
                    ->setEmail('johndoe' . $i . '@example.com')
                    ->setWebsiteId(1)
                    ->setGroupId(1)
                    ->setStoreId(1);

                $customer->setCustomAttribute('password_hash', $encryptor->getHash('Password123', true));

                $customerRepository->save($customer);
            }

            $output->writeln('Import complete!');
        } catch (LocalizedException $e) {
            $output->writeln('Error: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
