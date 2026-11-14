<?php

namespace App\Http\Requests\Merge;

use App\Services\Merge\MergeDefinition;
use App\Services\Merge\MergeRequestData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /{registries|referents}/merge (spec 0394). Shape only: that
 * the chosen ids/keys belong to this merge is checked by MergeChoiceResolver
 * against the real records. Authorization stays in the controller.
 */
class MergeRecordsRequest extends FormRequest
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
        $table = (string) $this->route('entity');

        return [
            'survivor_id' => ['required', 'integer', Rule::exists($table, 'id')],
            'merged_ids' => ['required', 'array', 'min:1', 'max:'.(MergeDefinition::MAX_RECORDS - 1)],
            'merged_ids.*' => ['integer', 'distinct', 'different:survivor_id', Rule::exists($table, 'id')],
            'fields' => ['sometimes', 'array'],
            'fields.*' => ['required', static function (string $attribute, mixed $value, callable $fail): void {
                if ($value !== MergeRequestData::COMBINE && ! (is_int($value) || (is_string($value) && ctype_digit($value)))) {
                    $fail(__('The field choice must be a record id or "combine".'));
                }
            }],
            'discard' => ['sometimes', 'array'],
            'discard.*' => ['array'],
            'discard.*.*' => ['integer'],
        ];
    }

    public function toData(): MergeRequestData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new MergeRequestData(
            survivorId: (int) $validated['survivor_id'],
            mergedIds: array_map('intval', $validated['merged_ids']),
            fields: array_map(static fn (mixed $choice): int|string => $choice === MergeRequestData::COMBINE ? $choice : (int) $choice, $validated['fields'] ?? []),
            discard: array_map(static fn (array $ids): array => array_map('intval', $ids), $validated['discard'] ?? []),
        );
    }
}
