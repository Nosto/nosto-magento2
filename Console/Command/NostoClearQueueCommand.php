<?php

namespace Nosto\Tagging\Console\Command;

use Magento\Framework\Amqp\Config;
use Magento\Framework\App\ResourceConnection;
use Nosto\NostoException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NostoClearQueueCommand extends Command
{
    /**
     * Nosto Queues
     */
    private const QUEUE_TOPICS = [
        'nosto_product_sync.update',
        'nosto_product_sync.delete'
    ];

    /**
     * @var Config
     */
    private $amqpConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection,
        Config $amqpConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->amqpConfig = $amqpConfig;
        parent::__construct();
    }

    protected function configure()
    {
        // Define command name.
        $this->setName('nosto:clear:queue')
            ->setDescription('Clear all message queues for Nosto product sync topics.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            foreach (self::QUEUE_TOPICS as $topicName) {
                $this->clearQueue($topicName, $io);
            }

            $io->success('Successfully cleared message queues.');
            return 0;
        } catch (NostoException $e) {
            $io->error('An error occurred while clearing message queues: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear MySql and RabbitMq queues by name.
     *
     * @param string $topicName
     * @param SymfonyStyle $io
     * @return void
     */
    private function clearQueue(string $topicName, SymfonyStyle $io): void
    {
        $this->clearRabbitMQQueue($topicName, $io);
        $this->clearDBQueues($topicName, $io);
    }

    /**
     * Clear DB.
     *
     * @param string $topicName
     * @param SymfonyStyle $io
     * @return void
     */
    private function clearDBQueues(string $topicName, SymfonyStyle $io): void
    {
        // Get connection.
        $connection = $this->resourceConnection->getConnection();

        // Start DB transaction.
        $connection->beginTransaction();
        try {
            // Emptying DB tables.
            $this->clearQueueMessages($topicName, $connection);
            $this->clearRelatedRecords($topicName, $connection);
            $connection->commit();
        } catch (\Exception $exception) {
            $connection->rollBack();
            $io->error('An error occurred while clearing DB queues for topic '
                . $topicName . ': '
                . $exception->getMessage()
            );
        }
    }

    /**
     * Emptying queue message tables.
     *
     * @param string $topicName
     * @param $connection
     * @return void
     */
    private function clearQueueMessages(string $topicName, $connection): void
    {
        $queueMessageTable = $this->resourceConnection->getTableName('queue_message');
        $queueMessageStatusTable = $this->resourceConnection->getTableName('queue_message_status');

        // Get all IDs from "queue_message" table.
        $select = $connection->select()
            ->from($queueMessageTable, ['id'])
            ->where('topic_name = ?', $topicName);
        $messageIds = $connection->fetchCol($select);

        // Delete related records from "queue_message_status" table.
        if (!empty($messageIds)) {
            $connection->delete($queueMessageStatusTable, ['message_id IN (?)' => $messageIds]);
        }

        // Delete records from "queue_message" table.
        $connection->delete($queueMessageTable, ['topic_name = ?' => $topicName]);
    }

    /**
     * Emptying related tables.
     *
     * @param string $topicName
     * @param $connection
     * @return void
     */
    private function clearRelatedRecords(string $topicName, $connection): void
    {
        $magentoOperationTable = $this->resourceConnection->getTableName('magento_operation');
        $magentoBulkTable = $this->resourceConnection->getTableName('magento_bulk');

        // Get all IDs from "magento_operation" table.
        $selectBulkUuids = $connection->select()
            ->from($magentoOperationTable, ['bulk_uuid'])
            ->where('topic_name = ?', $topicName);
        $bulkUuids = $connection->fetchCol($selectBulkUuids);

        // Delete related records from "magento_bulk" table.
        if (!empty($bulkUuids)) {
            $connection->delete($magentoBulkTable, ['uuid IN (?)' => $bulkUuids]);
        }

        // Delete records from "magento_operation" table.
        $connection->delete($magentoOperationTable, ['topic_name = ?' => $topicName]);
    }

    /**
     * Clear RabbitMq Queues by name.
     *
     * @param string $queueName
     * @param SymfonyStyle $io
     * @return void
     */
    private function clearRabbitMQQueue(string $queueName, SymfonyStyle $io): void
    {
        try {
            // Get RabbitMq channel.
            $channel = $this->amqpConfig->getChannel();

            // Empty queue if queue exists.
            if ($this->queueExists($channel, $queueName)) {
                $channel->queue_purge($queueName);
            }
        } catch (\Exception $e) {
            // Log the error or handle it as required.
            $io->error('An error occurred while clearing RabbitMQ queue ' . $queueName . ': ' . $e->getMessage());
            throw new \RuntimeException('Failed to clear RabbitMQ queue: ' . $e->getMessage());
        }
    }

    /**
     * Check queue exist.
     *
     * @param $channel
     * @param string $queueName
     * @return bool
     */
    protected function queueExists($channel, string $queueName): bool
    {
        $queueInfo = $channel->queue_declare($queueName, true);

        return !empty($queueInfo);
    }
}
