<?php

namespace App\Services\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class EventPublisher
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
    }

    public function publish(string $routingKey, array $payload): void
    {
        $message = new AMQPMessage(
            json_encode($payload),
            ['content_type' => 'application/json', 'delivery_mode' => 2]
        );

        $this->channel->basic_publish($message, $this->exchange, $routingKey);
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
