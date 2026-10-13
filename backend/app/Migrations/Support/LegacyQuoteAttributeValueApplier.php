<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\Quote;
use App\Models\User;
use App\Quotes\QuoteAttributeResolver;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeValueValidator;
use App\Services\Quotes\QuoteAttributeValueWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Writes the legacy "Info preliminari" values onto a freshly imported Quote
 * (spec 0189, G-17). Only the codes applicable to the quote's own offer-line
 * categories are kept, each validated on its own so one bad value never costs
 * the others nor the row: a non-applicable code, an invalid value or an
 * unmigrated related record is skipped with a warning. The map is merged in
 * memory through QuoteAttributeValueWriter; the caller's final quiet save
 * persists it.
 */
final class LegacyQuoteAttributeValueApplier
{
    /**
     * Relation targets the legacy sends as its own ids, remapped via `old_id`.
     *
     * @var array<string, class-string<Model>>
     */
    private const array LEGACY_RELATION_TARGETS = [
        'users' => User::class,
    ];

    public function __construct(
        private readonly QuoteAttributeResolver $attributeResolver,
        private readonly AttributeValueValidator $validator,
        private readonly QuoteAttributeValueWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $legacyValues  {code => legacy value}
     * @param  array<int, string>  $warnings
     */
    public function apply(Quote $quote, array $legacyValues, array &$warnings): void
    {
        if ($legacyValues === []) {
            return;
        }

        // Step 1: the set the quote's own offer lines make applicable.
        $applicable = $this->attributeResolver->resolve($quote)->keyBy('code');

        // Step 2: keep each value that fits its attribute, report the others.
        $values = $this->acceptedValues($applicable, $legacyValues, $warnings);

        if ($values === []) {
            return;
        }

        // Step 3: merge in memory, already validated against the same set.
        $changed = [];
        $old = [];
        $this->writer->apply($quote, $values, $changed, $old, $applicable->values());
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $applicable
     * @param  array<string, mixed>  $legacyValues
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function acceptedValues(Collection $applicable, array $legacyValues, array &$warnings): array
    {
        $accepted = [];
        $notApplicable = [];

        foreach ($legacyValues as $code => $value) {
            $attribute = $applicable->get($code);

            if ($attribute === null) {
                $notApplicable[] = $code;

                continue;
            }

            $value = $attribute->type === 'relation' ? $this->remapRelation($attribute, $value, $warnings) : $value;

            if ($value !== null && $this->isValid($applicable, (string) $code, $value, $warnings)) {
                $accepted[$code] = $value;
            }
        }

        if ($notApplicable !== []) {
            $warnings[] = 'Legacy additional information not applicable to the offer categories, not imported: '
                .implode(', ', $notApplicable).'.';
        }

        return $accepted;
    }

    /**
     * Legacy id(s) -> local id(s) via `old_id`; null (with a warning) when
     * nothing resolves or the target is not a migrated entity.
     *
     * @param  array<int, string>  $warnings
     */
    private function remapRelation(ApplicableAttribute $attribute, mixed $value, array &$warnings): mixed
    {
        $modelClass = self::LEGACY_RELATION_TARGETS[$attribute->relationTarget['entity_type'] ?? ''] ?? null;

        if ($modelClass === null) {
            $warnings[] = "Legacy additional information {$attribute->code} not imported: its target is not a migrated entity.";

            return null;
        }

        $legacyIds = (array) $value;
        $ids = $modelClass::query()->whereIn('old_id', $legacyIds)->pluck('id')->all();

        if (count($ids) !== count(array_unique($legacyIds))) {
            $warnings[] = "Legacy additional information {$attribute->code}: unresolved legacy id(s) ".implode(', ', $legacyIds).'.';
        }

        if ($ids === []) {
            return null;
        }

        return ($attribute->relationTarget['cardinality'] ?? 'one') === 'many' ? $ids : $ids[0];
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $applicable
     * @param  array<int, string>  $warnings
     */
    private function isValid(Collection $applicable, string $code, mixed $value, array &$warnings): bool
    {
        try {
            $this->validator->validate($applicable->values(), [$code => $value]);

            return true;
        } catch (ValidationException $exception) {
            $warnings[] = "Legacy additional information {$code} not imported: "
                .collect($exception->errors())->flatten()->first();

            return false;
        }
    }
}
