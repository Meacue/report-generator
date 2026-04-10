<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Domain\Shared\ValueObjects\TaskNumber;
use Carbon\Carbon;

final readonly class ParsedBranch
{
    public function __construct(
        public string $branchName,
        public ?string $parentBranch,
        public ?string $info,
        public ?TaskNumber $parsedTaskNumber,
        public ?Carbon $parsedDate,
        public ?string $parsedTime,
    ) {
    }

    /**
     * Whether the branch was successfully parsed.
     */
    public function isParsed(): bool
    {
        return $this->parentBranch !== null;
    }

    /**
     * Whether a task number was extracted.
     */
    public function hasTaskNumber(): bool
    {
        return $this->parsedTaskNumber !== null;
    }
}
