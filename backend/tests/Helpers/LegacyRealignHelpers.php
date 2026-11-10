<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OpenSpout\Reader\XLSX\Reader;

/*
 * Fixtures of the one-shot realignment (spec 0375): the two legacy calls of the
 * frozen contract, and a reader for the Excel it writes.
 */

if (! function_exists('fakeLegacyRealign')) {
    /**
     * Fakes `changed-records/{source}` and the source's `only_ids` listing.
     *
     * @param  list<int|string>  $changed
     * @param  list<int|string>  $deleted
     * @param  list<array<string, mixed>>  $items
     */
    function fakeLegacyRealign(string $source, array $changed, array $deleted = [], array $items = [], ?string $endpoint = null): void
    {
        seedMigrationsConfig();
        $base = fakeMigrationsBaseUrl();

        Http::fake([
            "{$base}/changed-records/{$source}*" => Http::response([
                'source' => $source,
                'since' => '2026-10-23',
                'changed' => $changed,
                'deleted' => $deleted,
            ]),
            "{$base}/".($endpoint ?? $source).'*' => fn (Request $request) => Http::response([
                'items' => $items,
                'pagination' => ['total' => count($items), 'offset' => 0, 'limit' => 100, 'total_pages' => 1],
            ]),
        ]);
    }
}

if (! function_exists('readLegacyRealignReport')) {
    /**
     * @return array<string, list<list<mixed>>> rows by sheet name
     */
    function readLegacyRealignReport(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $sheets;
    }
}

if (! function_exists('legacyRealignOutputPath')) {
    function legacyRealignOutputPath(): string
    {
        return sys_get_temp_dir().'/realign-'.uniqid('', true).'.xlsx';
    }
}
