<?php

namespace Nosto\Tagging\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\MessageQueue\Consumer\ConfigInterface as ConsumerConfig;
use Magento\Framework\MessageQueue\QueueRepository;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NostoClearQueueCommand extends Command
{
    /**
     * Nosto Product Sync Update label.
     *
     * @var string
     */
    public const NOSTO_UPDATE_SYNC_MESSAGE_QUEUE = 'nosto_product_sync.update';

    /**
     * Nosto Product Sync Delete label.
     *
     * @var string
     */
    public const NOSTO_DELETE_MESSAGE_QUEUE = 'nosto_product_sync.delete';

    /**
     * @var ConsumerConfig
     */
    private $consumerConfig;

    /**
     * @var QueueRepository
     */
    private $queueRepository;

    private array $consumers = [
        self::NOSTO_DELETE_MESSAGE_QUEUE,
        self::NOSTO_UPDATE_SYNC_MESSAGE_QUEUE,
    ];

    /**
     * NostoClearQueueCommand constructor.
     *
     * @param ConsumerConfig $consumerConfig
     * @param QueueRepository $queueRepository
     */
    public function __construct(
        ConsumerConfig $consumerConfig,
        QueueRepository $queueRepository
    ) {
        $this->consumerConfig = $consumerConfig;
        $this->queueRepository = $queueRepository;
        parent::__construct();
    }

    /**
     * Configure the command and the arguments
     */
    protected function configure()
    {
        $this->setName('nosto:clear:message-queue')
            ->setDescription('Clear all message queues for Nosto product sync topics.');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            foreach ($this->consumers as $queueName) {
                $this->clearQueue($io, $queueName);
            }
            $io->success('Successfully cleared message queues.');
        } catch (RuntimeException|LocalizedException $e) {
            $io->error('An error occurred while clearing message queues: ' . $e->getMessage());
            return 1;
        }
        return 0;
    }

    /**
     * Clear message queues by consumer name.
     *
     * @param SymfonyStyle $io
     * @param string $consumerName
     * @return void
     * @throws LocalizedException
     */
    private function clearQueue(SymfonyStyle $io, string $consumerName): void
    {
        $io->writeln(sprintf('Clearing messages from %s', $consumerName));
        $io->createProgressBar();
        $io->progressStart();
        $consumerConfig = $this->consumerConfig->getConsumer($consumerName);
        $queue = $this->queueRepository->get($consumerConfig->getConnection(), $consumerConfig->getQueue());
        while ($message = $queue->dequeue()) {
            $io->progressAdvance(1);
            $queue->acknowledge($message);
        }
        $io->progressFinish();
    }
}
