<?php

namespace App\Services\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQClient
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;
    private string $exchange;

    public function __construct()
    {
        $config = config('rabbitmq');

        $this->connection = new AMQPStreamConnection(
            $config['host'],
            (int) $config['port'],
            $config['user'],
            $config['password'],
            $config['vhost']
        );

        $this->channel = $this->connection->channel();
        $this->exchange = $config['exchange'];

        $this->channel->exchange_declare($this->exchange, 'topic', false, true, false);
        $this->channel->basic_qos(null, 1, null);
    }

    /**
     * Consume messages from the given queue and routing keys.
     *
     * @param string $queue
     * @param string|array $routingKeys
     * @param callable $handler
     */
    public function consume(string $queue, string|array $routingKeys, callable $handler): void
    {
        $this->channel->queue_declare($queue, false, true, false, false);
        $routingKeys = (array) $routingKeys;
        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_bind($queue, $this->exchange, $routingKey);
        }

        $callback = function (AMQPMessage $message) use ($handler) {
            try {
                $payload = json_decode($message->getBody(), true) ?? [];
                $handler($payload);
                $message->ack();
            } catch (\Throwable $e) {
                \Log::error('RabbitMQ consumer failed: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $message->nack(false, false);
            }
        };

        $this->channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function close(): void
    {
        $this->channel->close();
        $this->connection->close();
    }
}
