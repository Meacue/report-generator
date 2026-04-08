<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class NoDataException extends RuntimeException
{
    public function __construct(string $message = 'Нет данных для формирования отчёта. Выполните синхронизацию или проверьте настройки.')
    {
        parent::__construct($message, 422);
    }
}
