<?php

declare(strict_types=1);

namespace App\Domain\GitLab\Services;

use App\Domain\Shared\ValueObjects\TaskNumber;
use App\Domain\GitLab\DTOs\ParsedBranch;
use Carbon\Carbon;

class BranchParser
{
    /**
     * Primary regex: branch with date (and optional time).
     */
    private const PATTERN_WITH_DATE = '/^(?<parent>[^_]+)_(?<info>.+?)_(?<date>\d{2}\.\d{2}\.\d{4})(?:-(?<time>\d{2}(?:\.\d{2})?))?$/';

    /**
     * Fallback regex: branch without date.
     */
    private const PATTERN_WITHOUT_DATE = '/^(?<parent>[^_]+)_(?<info>.+)$/';

    /**
     * Parse a branch name into structured data.
     */
    public function parse(string $branchName): ParsedBranch
    {
        if (preg_match(self::PATTERN_WITH_DATE, $branchName, $matches) === 1) {
            return $this->buildFromDateMatch($branchName, $matches);
        }

        if (preg_match(self::PATTERN_WITHOUT_DATE, $branchName, $matches) === 1) {
            return $this->buildFromFallbackMatch($branchName, $matches);
        }

        return new ParsedBranch(
            branchName: $branchName,
            parentBranch: null,
            info: null,
            parsedTaskNumber: null,
            parsedDate: null,
            parsedTime: null,
        );
    }

    /**
     * Build ParsedBranch from a match with date.
     *
     * @param  array<int|string, string>  $matches
     */
    private function buildFromDateMatch(string $branchName, array $matches): ParsedBranch
    {
        $info = $matches['info'];
        $taskNumber = $this->extractTaskNumber($info);
        $date = $this->parseDate($matches['date']);
        $time = $this->normalizeTime(isset($matches['time']) && $matches['time'] !== '' ? $matches['time'] : null);

        return new ParsedBranch(
            branchName: $branchName,
            parentBranch: $matches['parent'],
            info: $info,
            parsedTaskNumber: $taskNumber,
            parsedDate: $date,
            parsedTime: $time,
        );
    }

    /**
     * Build ParsedBranch from a fallback match (no date).
     *
     * @param  array<int|string, string>  $matches
     */
    private function buildFromFallbackMatch(string $branchName, array $matches): ParsedBranch
    {
        $info = $matches['info'];
        $taskNumber = $this->extractTaskNumber($info);

        return new ParsedBranch(
            branchName: $branchName,
            parentBranch: $matches['parent'],
            info: $info,
            parsedTaskNumber: $taskNumber,
            parsedDate: null,
            parsedTime: null,
        );
    }

    /**
     * Extract task number if info is purely numeric.
     */
    private function extractTaskNumber(string $info): ?TaskNumber
    {
        if (preg_match('/^\d+$/', $info) === 1) {
            return new TaskNumber($info);
        }

        return null;
    }

    /**
     * Parse date string DD.MM.YYYY to Carbon, null if invalid.
     */
    private function parseDate(string $dateStr): ?Carbon
    {
        $parsed = Carbon::createFromFormat('d.m.Y', $dateStr);

        if ($parsed === null) {
            return null;
        }

        return $parsed->startOfDay();
    }

    /**
     * Normalize time: "16" -> "16:00", "16.30" -> "16:30", null -> null.
     */
    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        if (str_contains($time, '.')) {
            return str_replace('.', ':', $time);
        }

        return $time . ':00';
    }
}
