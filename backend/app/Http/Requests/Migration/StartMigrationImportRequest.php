<?php

namespace App\Http\Requests\Migration;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional `updated_since` day (spec 0345) for a legacy import: only rows
 * created or modified from that day on are requested. Absent or null = full
 * import.
 *
 * Authorization is NOT handled here: the route group's `super-admin`
 * middleware alias (EnsureSuperAdmin) gates every migrations endpoint.
 */
class StartMigrationImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function updatedSince(): ?CarbonImmutable
    {
        $day = $this->validated('updated_since');

        return $day === null ? null : CarbonImmutable::createFromFormat('Y-m-d', $day)->startOfDay();
    }
}
