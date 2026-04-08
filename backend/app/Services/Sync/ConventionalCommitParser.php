<?php

declare(strict_types=1);

namespace App\Services\Sync;

class ConventionalCommitParser
{
    private const PATTERN = '/^(?<type>feat|fix|chore|refactor|docs|test|style|perf|ci|build|revert)(?:\(.+?\))?[!]?:\s/';

    /**
     * Extract conventional commit type from commit message.
     */
    public function extractType(string $message): ?string
    {
        if (preg_match(self::PATTERN, $message, $matches) === 1) {
            return $matches['type'];
        }

        return null;
    }
}
