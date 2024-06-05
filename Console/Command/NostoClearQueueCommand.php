<?php

namespace Nosto\Tagging\Console\Command;

use Exception;
use Magento\Framework\Amqp\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Bulk\BulkManagementInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql\Interceptor;
use Nosto\NostoException;
use RuntimeException;
use PhpAmqpLib\Channel\AMQPChannel;
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
     * @var Config
     */
    private Config $amqpConfig;

    public function __construct(
        Config $amqpConfig
    ) {
        $this->amqpConfig = $amqpConfig;
        parent::__construct();
    }

    /**
     * Configure the command and the arguments
     */
    protected function configure()
    {
        // Define command name.
        $this->setName('nosto:clear:messagequeue')
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
            $queues = [
                self::NOSTO_DELETE_MESSAGE_QUEUE,
                self::NOSTO_UPDATE_SYNC_MESSAGE_QUEUE,
            ];

            foreach ($queues as $queueName) {
                $this->clearQueue($queueName, $io);
            }

            $io->success('Successfully cleared message queues.');
            return 0;
        } catch (RuntimeException $e) {
            $io->error('An error occurred while clearing message queues: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear MySql and RabbitMq queues by name.
     *
     * @param string $queueName
     * @return void
     */
    private function clearQueue(string $queueName): void
    {
        // Get RabbitMq channel.
        $channel = $this->amqpConfig->getChannel();

        // Empty queue if queue exists.
        if ($this->queueExists($channel, $queueName)) {
            $channel->queue_purge($queueName);
        }
    }

    /**
     * Check the expected queue exist.
     *
     * @param AMQPChannel $channel
     * @param string $queueName
     * @return bool
     */
    protected function queueExists(AMQPChannel $channel, string $queueName): bool
    {
        $queueInfo = $channel->queue_declare($queueName, true);

        return !empty($queueInfo);
    }
}
