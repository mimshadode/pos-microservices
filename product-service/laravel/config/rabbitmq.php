<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'pos'),
    'password' => env('RABBITMQ_PASSWORD', 'pos123'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'pos.events'),
    'queues' => [
        'order_created' => env('RABBITMQ_QUEUE_ORDER_CREATED', 'order.created'),
    ],
];
