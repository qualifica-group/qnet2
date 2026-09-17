<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0139 D-6: the leads import has no `mobile` field any more. Stored
 * mappings (templates and runs) and staged rows that still name it are
 * remapped onto `phone` when that field is free, otherwise the `mobile` entry
 * is dropped — so a saved template or a run under review never carries a
 * field id the definition no longer accepts.
 */
return new class extends Migration
{
    private const string RESOURCE = 'leads';

    private const string MOBILE = 'mobile';

    private const string PHONE = 'phone';

    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        // Step 1: column_mapping (column key => field id) of templates and runs
        foreach (['import_mapping_templates', 'import_runs'] as $table) {
            $this->remapColumnMappings($table);
        }
        // Step 2: staged rows' mapped_values (field id => value)
        $this->remapStagedRows();
    }

    /**
     * Irreversible (spec 0139 D-9): the `mobile` field no longer exists.
     */
    public function down(): void {}

    private function remapColumnMappings(string $table): void
    {
        DB::table($table)
            ->select(['id', 'column_mapping'])
            ->where('resource', self::RESOURCE)
            ->where('column_mapping', 'like', '%"'.self::MOBILE.'"%')
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $mapping = json_decode((string) $row->column_mapping, true);

                    if (! is_array($mapping) || ! in_array(self::MOBILE, $mapping, true)) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        'column_mapping' => json_encode($this->remapMapping($mapping)),
                    ]);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @return array<string, mixed>
     */
    private function remapMapping(array $mapping): array
    {
        $phoneMapped = in_array(self::PHONE, $mapping, true);

        foreach ($mapping as $columnKey => $fieldId) {
            if ($fieldId !== self::MOBILE) {
                continue;
            }

            if ($phoneMapped) {
                unset($mapping[$columnKey]);

                continue;
            }

            $mapping[$columnKey] = self::PHONE;
            $phoneMapped = true;
        }

        return $mapping;
    }

    private function remapStagedRows(): void
    {
        DB::table('import_run_rows')
            ->select(['import_run_rows.id', 'import_run_rows.mapped_values'])
            ->join('import_runs', 'import_runs.id', '=', 'import_run_rows.import_run_id')
            ->where('import_runs.resource', self::RESOURCE)
            ->where('import_run_rows.mapped_values', 'like', '%"'.self::MOBILE.'"%')
            ->chunkById(self::CHUNK_SIZE, function ($rows): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->mapped_values, true);

                    if (! is_array($values) || ! array_key_exists(self::MOBILE, $values)) {
                        continue;
                    }

                    $phone = trim((string) ($values[self::PHONE] ?? ''));

                    if ($phone === '') {
                        $values[self::PHONE] = $values[self::MOBILE];
                    }

                    unset($values[self::MOBILE]);

                    DB::table('import_run_rows')->where('id', $row->id)->update([
                        'mapped_values' => json_encode($values),
                    ]);
                }
            }, 'import_run_rows.id', 'id');
    }
};
