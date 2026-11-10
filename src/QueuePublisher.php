<?php
declare(strict_types=1);

namespace App;





final class QueuePublisher
{
    public function __construct(private readonly QueueConnectionFactory $factory, private readonly string $queue)
    {
    }

    public function publish(StatementRequest $request): void
    {
        $connection = $this->factory->create();
        try {
            $channel = new \AMQPChannel($connection);
            $queue = new \AMQPQueue($channel);
            $queue->setName($this->queue);
            $queue->setFlags(AMQP_DURABLE);
            $queue->declareQueue();
            $exchange = new \AMQPExchange($channel);
            $exchange->setName('');
            $exchange->publish(
                json_encode($request->toArray(), JSON_THROW_ON_ERROR),
                $this->queue,
                AMQP_NOPARAM,
                [
                    'delivery_mode' => 2,
                    'content_type' => 'application/json',
                    'message_id' => $request->id,
                ],
            );
        } finally {
            $connection->disconnect();
        }
    }
}
