<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ConsumeOrderEvents;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rabbitmq:consume-orders', function (ConsumeOrderEvents $command) {
    $command->handle();
})->purpose('Consume OrderCreated events from RabbitMQ');
