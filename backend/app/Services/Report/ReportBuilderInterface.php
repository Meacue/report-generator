<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Models\Report;

interface ReportBuilderInterface
{
    /**
     * Generate a report for a given period.
     */
    public function generate(string $type, DateRange $dateRange): Report;

    /**
     * Get report preview data with all days and tasks.
     *
     * @return array{
     *     id: int,
     *     type: string,
     *     date_from: string,
     *     date_to: string,
     *     status: string,
     *     days: array<int, array{
     *         date: string,
     *         narrative: string|null,
     *         source: string,
     *         is_edited: bool,
     *         tasks: array<int, array{
     *             id: int|null,
     *             title: string,
     *             project_name: string|null,
     *             narrative: string|null,
     *             is_edited: bool
     *         }>
     *     }>,
     *     tasks: list<array{id: int, task_id: int|null, narrative: string|null, project_name: string, is_edited: bool, task: array{id: int, bitrix24_task_id: int|null, title: string, status: string}|null}>
     * }
     */
    public function getPreview(Report $report): array;
}
