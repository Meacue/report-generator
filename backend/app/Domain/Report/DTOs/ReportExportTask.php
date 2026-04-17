<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportTask
{
    public string $title;

    public function __construct(
        string|null $title,
        public string $projectName = '',
        public string $narrative = '',
        public int|string|null $number = null,
    ) {
        // Stub tasks (403/404 from Bitrix24) have a null title; coerce to an
        // empty string so downstream rendering can apply its own label logic.
        $this->title = $title ?? '';
    }
}
