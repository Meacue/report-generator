<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportTask
{
    public function __construct(
        public string $title,
        public string $projectName = '',
        public string $narrative = '',
        public int|string|null $number = null,
    ) {
    }
}
