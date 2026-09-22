<?php

declare(strict_types=1);

namespace App\Http\Requests\TaskTemplates\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared `stages.*` rules for Store/UpdateTaskTemplateRequest (spec 0146,
 * D-2): the row shape is IDENTICAL between the two, only whether
 * `stages.*.id` may be present differs — mirroring
 * ValidatesTaskTemplateItems's own `$allowIds` split.
 *
 * `stages` itself is `sometimes`: omitting the key entirely leaves the
 * template's existing stages untouched (TaskTemplateService::update()); an
 * EMPTY array is a legitimate full-sync ("remove every stage", D-2), so no
 * `min:1` here — unlike `items`, which the model requires at least one row
 * of.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesTaskTemplateStages
{
    private const int STAGES_MAX = 50;

    private const int STAGE_KEY_MAX = 191;

    private const int STAGE_NAME_MAX = 191;

    /**
     * @param  bool  $allowIds  update passes true (`stages.*.id` identifies
     *                          an existing row, ownership asserted separately
     *                          — see UpdateTaskTemplateRequest); store passes
     *                          false (`stages.*.id` is `prohibited`, D-2).
     * @return array<string, array<int, mixed>>
     */
    protected function stagesRules(bool $allowIds): array
    {
        return [
            'stages' => ['sometimes', 'array', 'max:'.self::STAGES_MAX],
            'stages.*.id' => $allowIds ? ['sometimes', 'nullable', 'integer'] : ['prohibited'],
            'stages.*.key' => ['required', 'string', 'max:'.self::STAGE_KEY_MAX, 'distinct'],
            'stages.*.name' => ['required', 'string', 'max:'.self::STAGE_NAME_MAX],
        ];
    }

    /**
     * Every non-null `items.*.stage_key` must match a `stages.*.key`
     * submitted in the SAME request (data_contract, AC-003): `stages`
     * omitted from this request means no key can ever resolve, so a
     * `stage_key` still 422s even against a stage that exists on the
     * template from an earlier request.
     */
    protected function assertItemStageKeysResolve(Validator $validator): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $stages = $this->input('stages');
        $submittedKeys = is_array($stages) ? $this->submittedStageKeys($stages) : [];

        foreach ($items as $index => $row) {
            $stageKey = is_array($row) ? ($row['stage_key'] ?? null) : null;

            if ($stageKey !== null && ! in_array($stageKey, $submittedKeys, true)) {
                $validator->errors()->add("items.{$index}.stage_key", 'This stage key was not submitted in stages.');
            }
        }
    }

    /**
     * @param  array<int, mixed>  $stages
     * @return array<int, string>
     */
    private function submittedStageKeys(array $stages): array
    {
        $keys = [];

        foreach ($stages as $row) {
            if (is_array($row) && is_string($row['key'] ?? null)) {
                $keys[] = $row['key'];
            }
        }

        return $keys;
    }
}
