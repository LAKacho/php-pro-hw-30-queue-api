<?php
declare(strict_types=1);

namespace App;






final class RequestRepository
{
    private const ID_PATTERN = '/^[a-f0-9]{16}$/';

    public function __construct(private readonly string $directory)
    {
    }

    public function save(string $id, array $data): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
        $target = $this->path($id);
        $temporary = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        rename($temporary, $target);
    }

    public function find(string $id): ?array
    {
        if (!preg_match(self::ID_PATTERN, $id) || !is_file($this->path($id))) {
            return null;
        }
        $data = json_decode((string)file_get_contents($this->path($id)), true);
        return is_array($data) ? $data : null;
    }

    




    public function findAll(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }
        $items = [];
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                $items[] = $data;
            }
        }
        usort($items, static fn(array $a, array $b): int => ($a['request']['createdAt'] ?? '') <=> ($b['request']['createdAt'] ?? ''));
        return $items;
    }

    private function path(string $id): string
    {
        return rtrim($this->directory, '/') . '/' . $id . '.json';
    }
}
