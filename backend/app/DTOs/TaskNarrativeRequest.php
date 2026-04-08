<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TaskNarrativeRequest
{
    /**
     * @param  array<int, string>  $commits
     * @param  array<int, string>  $changedFiles
     * @param  array<int, string>  $previousNarratives
     */
    public function __construct(
        public string $taskTitle,
        public string $projectName,
        public array $commits,
        public ?string $systemPrompt = null,
        public ?string $mrTitle = null,
        public ?string $mrDescription = null,
        public ?int $totalAdditions = null,
        public ?int $totalDeletions = null,
        public array $changedFiles = [],
        public array $previousNarratives = [],
    ) {
    }
}
