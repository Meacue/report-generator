<?php

declare(strict_types=1);

namespace App\Domain\Matching\DTOs;

final readonly class UnclassifiedCommit
{
    public function __construct(
        public string $repoName,
        public string $message,
        public string $branchName,
    ) {
    }
}
