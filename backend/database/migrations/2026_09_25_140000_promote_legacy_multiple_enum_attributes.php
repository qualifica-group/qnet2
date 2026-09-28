<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * L'import q-crm esprime un enum a scelta multipla come `{"display":
 * "select", "multiple": true}` ("Categorie ME.PA."): nessun livello legge
 * `multiple` (validatore, normalizzatore, colonne, form riconoscono solo
 * `display: multiselect`), quindi il campo si comportava da scelta singola.
 *
 * `up()` porta il display a `multiselect` e avvolge in una lista ogni valore
 * scalare gia' salvato, altrimenti fallirebbe la regola `array` al prossimo
 * salvataggio (stesso passo di QualificaContactProcessingSeeder::promoteDegree
 * per "Titolo di Studio"). `multiple` resta: e' coerente col nuovo display ed
 * e' il marcatore che permette alla `down()` di ritrovare gli attributi.
 *
 * La `down()` e' lossy per costruzione: una scelta singola tiene UN codice,
 * quindi da ogni lista sopravvive il primo.
 */
return new class extends Migration
{
    private const array VALUE_TABLES = ['quotes', 'work_orders', 'products'];

    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        foreach ($this->legacyMultipleAttributes('select') as $attribute) {
            $this->setDisplay($attribute, 'multiselect');

            foreach (self::VALUE_TABLES as $table) {
                $this->rewriteValues($table, $attribute->code, static fn (mixed $value): mixed => is_scalar($value) ? [(string) $value] : $value);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->legacyMultipleAttributes('multiselect') as $attribute) {
            $this->setDisplay($attribute, 'select');

            foreach (self::VALUE_TABLES as $table) {
                $this->rewriteValues($table, $attribute->code, static fn (mixed $value): mixed => is_array($value) ? ($value[0] ?? null) : $value);
            }
        }
    }

    /**
     * Filtered in PHP rather than with a JSON path: the catalogue is small and
     * the same code runs on MySQL and on the SQLite test database.
     *
     * @return Collection<int, object{id: int, code: string, config: array<string, mixed>}>
     */
    private function legacyMultipleAttributes(string $display): Collection
    {
        return DB::table('attributes')
            ->where('type', 'enum')
            ->get(['id', 'code', 'config'])
            ->map(static function (object $row): object {
                $row->config = json_decode((string) $row->config, true) ?? [];

                return $row;
            })
            ->filter(static fn (object $row): bool => ($row->config['multiple'] ?? false) === true
                && ($row->config['display'] ?? null) === $display)
            ->values();
    }

    private function setDisplay(object $attribute, string $display): void
    {
        DB::table('attributes')
            ->where('id', $attribute->id)
            ->update(['config' => json_encode([...$attribute->config, 'display' => $display])]);
    }

    /**
     * Straight on the table, not through the models: a type conversion is not
     * an edit of the record and must not flood its activity log.
     */
    private function rewriteValues(string $table, string $code, Closure $convert): void
    {
        DB::table($table)
            ->whereNotNull("attribute_values->{$code}")
            ->select(['id', 'attribute_values'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $rows) use ($table, $code, $convert): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->attribute_values, true);

                    if (! is_array($values) || ! array_key_exists($code, $values)) {
                        continue;
                    }

                    $converted = $convert($values[$code]);

                    if ($converted === $values[$code]) {
                        continue;
                    }

                    $values[$code] = $converted;
                    DB::table($table)->where('id', $row->id)->update(['attribute_values' => json_encode($values)]);
                }
            });
    }
};
