<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ConsumePaymentEvents;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rabbitmq:consume-payments', function (ConsumePaymentEvents $command) {
    $command->handle();
})->purpose('Consume PaymentCompleted events from RabbitMQ');
