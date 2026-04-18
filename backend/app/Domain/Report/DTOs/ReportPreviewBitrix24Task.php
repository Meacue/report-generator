<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use JsonSerializable;

final readonly class ReportPreviewBitrix24Task implements JsonSerializable
{
    public string $title;

    public function __construct(
        public int $id,
        public ?int $bitrix24TaskId,
        string|null $title,
        public string $status,
        public ?int $secondsTracked = null,
    ) {
        // Stub tasks (403/404 from Bitrix24) have a null title; coerce to an
        // empty string so downstream rendering can apply its own label logic.
        $this->title = $title ?? '';
    }

    /**
     * @return array{id: int, bitrix24_task_id: int|null, title: string, status: string, seconds_tracked: int|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'id'               => $this->id,
            'bitrix24_task_id' => $this->bitrix24TaskId,
            'title'            => $this->title,
            'status'           => $this->status,
            'seconds_tracked'  => $this->secondsTracked,
        ];
    }
}
