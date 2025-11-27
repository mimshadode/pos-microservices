<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'pos'),
    'password' => env('RABBITMQ_PASSWORD', 'pos123'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'pos.events'),
    'queues' => [
        'payment_completed' => env('RABBITMQ_QUEUE_PAYMENT_COMPLETED', 'payment.completed'),
    ],
];
