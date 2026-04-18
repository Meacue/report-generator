<?php

declare(strict_types=1);

namespace App\Domain\Bitrix24\DTOs;

use Carbon\CarbonImmutable;

final readonly class TimeEntryData
{
    public function __construct(
        public int $bitrix24EntryId,
        public int $bitrix24TaskId,
        public string $bitrix24UserId,
        public int $seconds,
        public ?string $comment,
        public CarbonImmutable $trackedAt,
        public ?CarbonImmutable $sourceCreatedAt,
    ) {
    }
}
