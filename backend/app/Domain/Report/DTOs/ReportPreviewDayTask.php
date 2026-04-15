<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use JsonSerializable;

final readonly class ReportPreviewDayTask implements JsonSerializable
{
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $projectName,
        public ?string $narrative,
        public bool $isEdited,
    ) {
    }

    /**
     * @return array{id: int|null, title: string, project_name: string|null, narrative: string|null, is_edited: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'project_name' => $this->projectName,
            'narrative'    => $this->narrative,
            'is_edited'    => $this->isEdited,
        ];
    }
}
