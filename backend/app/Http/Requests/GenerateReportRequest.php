<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Shared\ValueObjects\DateRange;
use App\Domain\Report\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, In|string>>
     */
    public function rules(): array
    {
        return [
            'type'      => ['required', Rule::in(array_column(ReportType::cases(), 'value'))],
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:' . $this->maxDateTo()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.before_or_equal' => 'The report period cannot exceed 30 days.',
        ];
    }

    public function toDateRange(): DateRange
    {
        /** @var array{date_from: string, date_to: string} $validated */
        $validated = $this->validated();

        return new DateRange($validated['date_from'], $validated['date_to']);
    }

    /**
     * Compute the maximum allowed date_to based on date_from + 30 days.
     * Returns the raw date_from string (so the rule is effectively skipped)
     * when date_from is absent or invalid — other rules will catch that.
     */
    private function maxDateTo(): string
    {
        $dateFrom = $this->input('date_from');

        if (! is_string($dateFrom) || $dateFrom === '') {
            return date('Y-m-d');
        }

        try {
            return date('Y-m-d', strtotime($dateFrom . ' +29 days') ?: 0);
        } catch (\Throwable) {
            return date('Y-m-d');
        }
    }
}
