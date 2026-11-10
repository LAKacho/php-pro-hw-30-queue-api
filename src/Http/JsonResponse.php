<?php
declare(strict_types=1);

namespace App\Http;




final class JsonResponse
{
    public static function send(int $status, array $body, array $headers = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        
        
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(int $status, string $message, array $details = []): void
    {
        self::send($status, ['error' => ['message' => $message, 'details' => $details ?: null]]);
    }
}
