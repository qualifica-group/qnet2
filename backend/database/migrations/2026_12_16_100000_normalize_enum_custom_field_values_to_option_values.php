<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data repair: the legacy product import sent the "Cartella" folder as its
 * LABEL ("Consulenza"/"Ente") and the generic custom-field persistence stored
 * it verbatim, while the enum options hold the VALUE ("consulenza"/"ente").
 * The edit form then refused every save of those products ("Seleziona un
 * valore valido"). The legacy now sends the value; this rewrites what was
 * already imported. A stored enum value that is not an option value but
 * matches one (value first, then label) case-insensitively is replaced by
 * that option value; anything else is left untouched. Single and multiselect
 * enums alike. down() is a no-op: the label form was never a valid value.
 */
return new class extends Migration
{
    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        DB::table('custom_field_definitions')
            ->where('type', 'enum')
            ->get(['id', 'entity_type', 'key'])
            ->each(fn (object $definition) => $this->normalizeDefinition($definition));
    }

    public function down(): void {}

    private function normalizeDefinition(object $definition): void
    {
        $lookup = $this->optionLookup((int) $definition->id);

        if ($lookup === []) {
            return;
        }

        DB::table('custom_field_values')
            ->where('entity_type', $definition->entity_type)
            ->select(['id', 'values'])
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($definition, $lookup): void {
                foreach ($rows as $row) {
                    $this->normalizeRow($row, $definition->key, $lookup);
                }
            });
    }

    /**
     * Lower-cased value and label => option value; a value match wins over a label match.
     *
     * @return array<string, string>
     */
    private function optionLookup(int $definitionId): array
    {
        $options = DB::table('custom_field_options')->where('definition_id', $definitionId)->get(['value', 'label']);
        $lookup = [];

        foreach ($options as $option) {
            $lookup[$this->normalizeText($option->label)] = $option->value;
        }

        foreach ($options as $option) {
            $lookup[$this->normalizeText($option->value)] = $option->value;
        }

        return $lookup;
    }

    /** @param  array<string, string>  $lookup */
    private function normalizeRow(object $row, string $key, array $lookup): void
    {
        $values = json_decode((string) $row->values, true);

        if (! is_array($values) || ! array_key_exists($key, $values)) {
            return;
        }

        $current = $values[$key];
        $normalized = is_array($current)
            ? array_map(fn (mixed $item): mixed => $this->normalizeValue($item, $lookup), $current)
            : $this->normalizeValue($current, $lookup);

        if ($normalized === $current) {
            return;
        }

        $values[$key] = $normalized;
        DB::table('custom_field_values')->where('id', $row->id)->update(['values' => json_encode($values)]);
    }

    /** @param  array<string, string>  $lookup */
    private function normalizeValue(mixed $value, array $lookup): mixed
    {
        if (! is_string($value) || in_array($value, $lookup, true)) {
            return $value;
        }

        return $lookup[$this->normalizeText($value)] ?? $value;
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(trim($text));
    }
};
