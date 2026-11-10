<?php
declare(strict_types=1);

namespace App\Http;

use App\InvalidRequestException;




final class RequestController
{
    public function __construct(
        private readonly \App\QueuePublisher $publisher,
        private readonly \App\RequestRepository $repository,
    ) {
    }

    



    public function create(array $input): void
    {
        try {
            $request = \App\StatementRequest::fromApiInput($input);
            $this->publisher->publish($request);
        } catch (InvalidRequestException $exception) {
            JsonResponse::error(400, $exception->getMessage(), $exception->getErrors());
            return;
        } catch (\Throwable $exception) {
            error_log('Queue publish failed: ' . $exception->getMessage());
            JsonResponse::error(503, 'Очередь недоступна, повторите запрос позже.');
            return;
        }

        $this->repository->save($request->id, ['status' => 'queued', 'request' => $request->toArray()]);
        JsonResponse::send(202, [
            'id' => $request->id,
            'status' => 'queued',
            'createdAt' => $request->createdAt,
            'statusUrl' => '/api/v1/requests/' . $request->id,
        ], ['Location' => '/api/v1/requests/' . $request->id]);
    }

    


    public function status(string $id): void
    {
        $record = $this->repository->find($id);
        if ($record === null) {
            JsonResponse::error(404, 'Заявка не найдена.');
            return;
        }

        $response = [
            'id' => $id,
            'status' => $record['status'],
            'request' => $record['request'] ?? null,
        ];
        if (($record['status'] ?? '') === 'completed') {
            $response['report'] = $record['report'] ?? null;
            $response['completedAt'] = $record['completedAt'] ?? null;
        }
        $response['statusUrl'] = '/api/v1/requests/' . $id;
        JsonResponse::send(200, $response);
    }

    


    public function list(): void
    {
        $items = [];
        foreach ($this->repository->findAll() as $record) {
            $items[] = [
                'id' => $record['request']['id'] ?? null,
                'status' => $record['status'],
                'createdAt' => $record['request']['createdAt'] ?? null,
                'statusUrl' => '/api/v1/requests/' . ($record['request']['id'] ?? ''),
            ];
        }
        JsonResponse::send(200, ['items' => $items, 'total' => count($items)]);
    }
}
