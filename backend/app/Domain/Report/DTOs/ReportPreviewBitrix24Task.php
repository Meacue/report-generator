<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use JsonSerializable;

final readonly class ReportPreviewBitrix24Task implements JsonSerializable
{
    public function __construct(
        public int $id,
        public ?int $bitrix24TaskId,
        public string $title,
        public string $status,
    ) {
    }

    /**
     * @return array{id: int, bitrix24_task_id: int|null, title: string, status: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id'               => $this->id,
            'bitrix24_task_id' => $this->bitrix24TaskId,
            'title'            => $this->title,
            'status'           => $this->status,
        ];
    }
}
