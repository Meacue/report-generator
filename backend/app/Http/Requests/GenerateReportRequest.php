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
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function toDateRange(): DateRange
    {
        /** @var array{date_from: string, date_to: string} $validated */
        $validated = $this->validated();

        return new DateRange($validated['date_from'], $validated['date_to']);
    }
}
