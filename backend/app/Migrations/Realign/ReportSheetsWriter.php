<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The Excel report of a one-off re-import command (spec 0378, 0398): one sheet per
 * entry, header first. The file holds personal data: it stays on the server.
 */
final class ReportSheetsWriter
{
    /**
     * @param  array<string, list<list<string|int|bool|null>>>  $sheets  sheet name => rows, header first
     */
    public static function write(string $path, array $sheets): void
    {
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $writer->close();
    }
}
