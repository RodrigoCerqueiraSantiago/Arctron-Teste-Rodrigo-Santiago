<?php

namespace App\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use RuntimeException;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class ConfirmedRabbitMQQueue extends RabbitMQQueue
{
    private ?AMQPChannel $confirmedChannel = null;

    protected function publishBasic($msg, $exchange = '', $destination = '', $mandatory = false, $immediate = false, $ticket = null): void
    {
        $channel = $this->getChannel();
        if ($this->confirmedChannel !== $channel) {
            $channel->confirm_select();
            $channel->set_nack_handler(fn () => throw new RuntimeException('RabbitMQ rejected a published message.'));
            $channel->set_return_listener(fn () => throw new RuntimeException('RabbitMQ could not route a published message.'));
            $this->confirmedChannel = $channel;
        }

        parent::publishBasic($msg, $exchange, $destination, $mandatory, $immediate, $ticket);
        $channel->wait_for_pending_acks_returns(10);
    }
}
