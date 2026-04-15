<?php

declare(strict_types=1);

namespace App\Domain\Report\DTOs;

use JsonSerializable;

final readonly class ReportPreview implements JsonSerializable
{
    /**
     * @param  list<ReportPreviewDay>  $days
     * @param  list<ReportPreviewTask>  $tasks
     */
    public function __construct(
        public int $id,
        public string $type,
        public string $dateFrom,
        public string $dateTo,
        public string $status,
        public array $days,
        public array $tasks,
    ) {
    }

    /**
     * @return array{id: int, type: string, date_from: string, date_to: string, status: string, days: list<ReportPreviewDay>, tasks: list<ReportPreviewTask>}
     */
    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
            'status'    => $this->status,
            'days'      => $this->days,
            'tasks'     => $this->tasks,
        ];
    }
}
