<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/tasks/{task}/reject (spec 0116, data_contract): no body.
 * Authorization stays in the controller (TaskPolicy::validate). Mirrors the
 * repo's precedent for a bodyless domain action,
 * App\Http\Requests\CompanySites\SetDefaultCompanySiteRequest.
 */
class RejectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
