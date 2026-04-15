<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportMonthlyTask
{
    public function __construct(
        public ReportExportTask $base,
        public ?int $id = null,
        public ?string $status = null,
        public ?string $bitrix24Link = null,
    ) {
    }
}
