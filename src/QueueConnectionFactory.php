<?php
declare(strict_types=1);

namespace App;

final class QueueConnectionFactory
{
    public function create(): \AMQPConnection
    {
        $connection = new \AMQPConnection([
            'host' => getenv('RABBITMQ_HOST') ?: 'rabbitmq',
            'port' => (int)(getenv('RABBITMQ_PORT') ?: 5672),
            'login' => getenv('RABBITMQ_USER') ?: 'app',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'secret',
            'vhost' => '/',
            'connect_timeout' => 5,
        ]);
        $connection->connect();
        return $connection;
    }
}
