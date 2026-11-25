<?php

declare(strict_types=1);

namespace Integrations\Formalab\Exports;

use Integrations\Formalab\Http\Requests\LearnerStatementRequest;
use Integrations\Formalab\Services\LearnerStatement\LearnerStatement;
use Integrations\Formalab\Services\LearnerStatement\StatementLearner;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The legacy Excel exports of the prospetto (spec 0427 AC-009): "Esporta
 * Excel" (one sheet, whole class, MOL and commission tables) and "Esporta
 * per Partner" (one sheet per partner with its learners and commissions,
 * MOL hidden).
 */
final class LearnerStatementExporter
{
    private const int SHEET_NAME_MAX = 31;

    private const int SHEET_NAME_DEDUP = 28;

    private const string SHEET_NAME_FORBIDDEN = '/[\\\\\/\*\?\[\]:]/';

    public function __construct(
        private readonly SummaryBlockWriter $summary,
        private readonly LearnersTableWriter $learners,
        private readonly CommissionTablesWriter $tables,
    ) {}

    /**
     * Writes the workbook at $path.
     *
     * @return string|null the download file name; null when the partner variant has no partner
     */
    public function write(LearnerStatement $statement, string $variant, string $path): ?string
    {
        $spreadsheet = new Spreadsheet;

        if ($variant === LearnerStatementRequest::VARIANT_PARTNERS) {
            if (! $this->writePartnerSheets($spreadsheet, $statement)) {
                return null;
            }
            $prefix = 'prospetto_partner_';
        } else {
            $this->writeWholeSheet($spreadsheet, $statement);
            $prefix = 'prospetto_contabile_';
        }

        (new Xlsx($spreadsheet))->save($path);

        return $prefix.$this->codeForFile($statement).'_'.now()->format('Y-m-d_His').'.xlsx';
    }

    private function writeWholeSheet(Spreadsheet $spreadsheet, LearnerStatement $statement): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Prospetto Contabile');

        $this->summary->write($sheet, $statement, null, 0);
        $table = $this->learners->write($sheet, $statement, $statement->calculator->learners(), null);
        $this->tables->write($sheet, $statement->calculator, $table['end_row'], $table['last_column']);
    }

    private function writePartnerSheets(Spreadsheet $spreadsheet, LearnerStatement $statement): bool
    {
        $learners = $statement->calculator->learners();
        $partners = array_values(array_unique(array_filter(array_map(static fn (StatementLearner $l): ?string => $l->partner, $learners))));

        if ($partners === []) {
            return false;
        }

        sort($partners);
        $spreadsheet->removeSheetByIndex(0);

        foreach ($partners as $index => $partner) {
            $own = array_values(array_filter($learners, static fn (StatementLearner $l): bool => $l->partner === $partner));
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->sheetName($spreadsheet, $partner, $index));

            $this->summary->write($sheet, $statement, $partner, count($own));
            $this->learners->write($sheet, $statement, $own, $partner);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return true;
    }

    /** Excel sheet names: no \ / * ? [ ] :, at most 31 characters, unique. */
    private function sheetName(Spreadsheet $spreadsheet, string $partner, int $index): string
    {
        $name = mb_substr((string) preg_replace(self::SHEET_NAME_FORBIDDEN, '_', $partner), 0, self::SHEET_NAME_MAX);

        return $spreadsheet->sheetNameExists($name) ? mb_substr($name, 0, self::SHEET_NAME_DEDUP).'_'.($index + 1) : $name;
    }

    private function codeForFile(LearnerStatement $statement): string
    {
        $code = $statement->classCode();

        return $code === null ? 'export' : (string) preg_replace('/[^a-zA-Z0-9]/', '_', $code);
    }
}
