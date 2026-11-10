<?php
declare(strict_types=1);

use App\Http\JsonResponse;
use App\Http\RequestController;
use App\Http\Router;
use App\QueueConnectionFactory;
use App\QueuePublisher;
use App\RequestRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

$repository = new RequestRepository(getenv('STORAGE_DIR') ?: dirname(__DIR__) . '/var/requests');
$controller = new RequestController(
    new QueuePublisher(new QueueConnectionFactory(), getenv('RABBITMQ_QUEUE') ?: 'statement_requests'),
    $repository,
);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router = new Router();

$router->add('POST', '/api/v1/requests', static function () use ($controller): void {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($body)) {
        JsonResponse::error(400, 'Ожидается JSON-тело запроса.');
        return;
    }
    $controller->create($body);
});

$router->add('GET', '/api/v1/requests', static function () use ($controller): void {
    $controller->list();
});

$router->add('GET', '/api/v1/requests/{id}', static function (array $params) use ($controller): void {
    $controller->status((string)($params['id'] ?? ''));
});

$router->add('GET', '/docs', static function (): void {
    require __DIR__ . '/docs.html';
});

$router->add('GET', '/api/health', static function () use ($repository): void {
    JsonResponse::send(200, ['status' => 'ok', 'requests' => count(glob((getenv('STORAGE_DIR') ?: dirname(__DIR__) . '/var/requests') . '/*.json') ?: [])]);
});

$match = $router->match($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
if ($match === null) {
    $allowed = $router->allowedMethods($path);
    if ($allowed !== []) {
        header('Allow: ' . implode(', ', $allowed));
        JsonResponse::error(405, 'Метод не поддерживается для этого ресурса.');
    } else {
        JsonResponse::error(404, 'Маршрут не найден. Список эндпоинтов: /docs');
    }
    return;
}

($match['handler'])($match['params']);
