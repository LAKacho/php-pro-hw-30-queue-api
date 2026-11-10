<?php
declare(strict_types=1);

namespace App;





final class InvalidRequestException extends \RuntimeException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Некорректное тело запроса.');
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
