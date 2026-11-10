<?php
declare(strict_types=1);

use App\QueueConnectionFactory;
use App\RequestRepository;
use App\StatementGenerator;
use App\StatementRequest;

require dirname(__DIR__) . '/src/bootstrap.php';

$queueName = getenv('RABBITMQ_QUEUE') ?: 'statement_requests';
$repository = new RequestRepository(getenv('STORAGE_DIR') ?: dirname(__DIR__) . '/var/requests');
$connection = (new QueueConnectionFactory())->create();
$channel = new AMQPChannel($connection);
$channel->setPrefetchCount(1);
$queue = new AMQPQueue($channel);
$queue->setName($queueName);
$queue->setFlags(AMQP_DURABLE);
$queue->declareQueue();
$generator = new StatementGenerator();

fwrite(STDOUT, "Worker started. Queue: {$queueName}\n");

$queue->consume(static function (AMQPEnvelope $message, AMQPQueue $queue) use ($repository, $generator): void {
    try {
        $request = StatementRequest::fromArray(json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR));
        fwrite(STDOUT, "Processing {$request->id}: {$request->dateFrom->format('Y-m-d')}..{$request->dateTo->format('Y-m-d')}\n");
        $repository->save($request->id, ['status' => 'processing', 'request' => $request->toArray()]);
        $report = $generator->generate($request);
        $repository->save($request->id, [
            'status' => 'completed',
            'request' => $request->toArray(),
            'report' => $report,
            'completedAt' => date(DATE_ATOM),
        ]);
        $queue->ack($message->getDeliveryTag());
        fwrite(STDOUT, "Completed {$request->id}\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Failed: ' . $exception->getMessage() . "\n");
        
        $queue->nack($message->getDeliveryTag(), AMQP_NOPARAM);
    }
});
