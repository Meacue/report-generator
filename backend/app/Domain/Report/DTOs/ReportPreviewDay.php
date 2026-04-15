<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use JsonSerializable;

final readonly class ReportPreviewDay implements JsonSerializable
{
    /**
     * @param  list<ReportPreviewDayTask>  $tasks
     */
    public function __construct(
        public string $date,
        public ?string $narrative,
        public string $source,
        public bool $isEdited,
        public array $tasks,
    ) {
    }

    /**
     * @return array{date: string, narrative: string|null, source: string, is_edited: bool, tasks: list<ReportPreviewDayTask>}
     */
    public function jsonSerialize(): array
    {
        return [
            'date'      => $this->date,
            'narrative' => $this->narrative,
            'source'    => $this->source,
            'is_edited' => $this->isEdited,
            'tasks'     => $this->tasks,
        ];
    }
}
