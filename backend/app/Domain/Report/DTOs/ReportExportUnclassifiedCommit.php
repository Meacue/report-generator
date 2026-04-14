<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

final readonly class ReportExportUnclassifiedCommit
{
    public function __construct(
        public string $repo,
        public string $message,
        public string $branch = '',
    ) {
    }
}
