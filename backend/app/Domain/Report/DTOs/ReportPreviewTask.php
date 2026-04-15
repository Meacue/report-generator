<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use JsonSerializable;

final readonly class ReportPreviewTask implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?int $taskId,
        public ?string $narrative,
        public string $projectName,
        public bool $isEdited,
        public ?ReportPreviewBitrix24Task $task,
    ) {
    }

    /**
     * @return array{id: int, task_id: int|null, narrative: string|null, project_name: string, is_edited: bool, task: ReportPreviewBitrix24Task|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'id'           => $this->id,
            'task_id'      => $this->taskId,
            'narrative'    => $this->narrative,
            'project_name' => $this->projectName,
            'is_edited'    => $this->isEdited,
            'task'         => $this->task,
        ];
    }
}
