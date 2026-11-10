<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use InvalidArgumentException;





final class StatementRequest
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly DateTimeImmutable $dateFrom,
        public readonly DateTimeImmutable $dateTo,
        public readonly string $createdAt,
    ) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Укажите корректный email.');
        }
        if ($dateFrom > $dateTo) {
            throw new InvalidArgumentException('Начальная дата не может быть позже конечной.');
        }
        if ($dateTo > new DateTimeImmutable('today')) {
            throw new InvalidArgumentException('Конечная дата не может быть в будущем.');
        }
    }

    



    public static function fromApiInput(array $input): self
    {
        $email = trim((string)($input['email'] ?? ''));
        $fromRaw = $input['dateFrom'] ?? $input['date_from'] ?? null;
        $toRaw = $input['dateTo'] ?? $input['date_to'] ?? null;

        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Email обязателен.';
        }
        $from = self::parseDate($fromRaw);
        if (!$from) {
            $errors['dateFrom'] = 'Дата начала обязательна, формат YYYY-MM-DD.';
        }
        $to = self::parseDate($toRaw);
        if (!$to) {
            $errors['dateTo'] = 'Дата окончания обязательна, формат YYYY-MM-DD.';
        }
        if ($errors !== []) {
            throw new InvalidRequestException($errors);
        }

        try {
            return new self(bin2hex(random_bytes(8)), $email, $from, $to, date(DATE_ATOM));
        } catch (InvalidArgumentException $exception) {
            throw new InvalidRequestException([$exception->getMessage()]);
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['email'],
            new DateTimeImmutable($data['dateFrom']),
            new DateTimeImmutable($data['dateTo']),
            $data['createdAt'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'dateFrom' => $this->dateFrom->format('Y-m-d'),
            'dateTo' => $this->dateTo->format('Y-m-d'),
            'createdAt' => $this->createdAt,
        ];
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($date && $date->format('Y-m-d') === $value) ? $date : null;
    }
}
