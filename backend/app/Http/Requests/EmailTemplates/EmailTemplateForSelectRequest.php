<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplates;

use App\DataObjects\Shared\ForSelectQuery;
use App\Enums\EmailTemplateModule;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/email-templates/for-select (ADR 0011,
 * spec 0175, D-14).
 *
 * Authorization is intentionally NOT handled here: NO gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31) — the composer needs this
 * list from an actor who may legitimately lack browse rights on the
 * "Modelli email" configurator itself. `module` is REQUIRED
 * (data_contract: "filtro obbligatorio").
 */
class EmailTemplateForSelectRequest extends FormRequest
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
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'module' => ['required', Rule::enum(EmailTemplateModule::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     *
     * The wire-format `module` is renamed to `email_template_module` before
     * reaching the SHARED ForSelectQuery::fromValidated(): the bare `module`
     * key is already claimed by DocumentLayoutForSelectRequest, whose own
     * (incompatible) DocumentLayoutModule enum would otherwise choke on
     * `work_orders` (see ForSelectQuery's own docblock).
     */
    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $validated['email_template_module'] = $validated['module'] ?? null;
        unset($validated['module']);

        return ForSelectQuery::fromValidated($validated);
    }
}
