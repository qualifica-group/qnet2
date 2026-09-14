<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Http\Requests\TimeEntries\Concerns\ValidatesTimeEntryFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET stats/overview and GET stats/pulse (spec 0122, data_contract): F,
 * exactly, no sort/pagination. Shared by both endpoints/controller methods
 * — same F shape, same rules, no reason for two classes.
 */
class TimeEntryStatsRequest extends FormRequest
{
    use ValidatesTimeEntryFilters;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->timeEntryFilterRules();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withTimeEntryFilterValidation($validator);
    }

    public function filter(): TimeEntryFilterData
    {
        return $this->timeEntryFilterData();
    }
}
