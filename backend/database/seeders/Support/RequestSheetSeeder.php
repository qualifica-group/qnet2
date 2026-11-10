<?php

namespace Database\Seeders\Support;

use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use Database\Seeders\Concerns\ResolvesSeedActor;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * The run shared by the seeders that load one of the user's "trattative" sheets
 * into Gestione Richieste (user directive 2026-11-07: seeders to run, not imports).
 * A concrete seeder names its category and hands its cleaned catalogue rows.
 *
 * Idempotent: a client already holding a request on the category is skipped, so a
 * second run writes nothing. Each row commits on its own; a row the domain refuses
 * is reported and left out. Assignment notifications stay silent, as in the import.
 */
abstract class RequestSheetSeeder extends Seeder
{
    use ResolvesSeedActor;

    private const string REPORTER_TYPE = 'Segnalatore';

    private const string COMMERCIAL_TYPE = 'Commerciale';

    public function __construct(
        protected readonly RequestSheetWriter $writer,
        protected readonly RequestSheetLookups $lookups,
    ) {}

    /** The sheet, as named in the summary. */
    abstract protected function label(): string;

    /** The category of the rows; a row with its own `category` (a name) overrides it. */
    abstract protected function categoryName(): string;

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function rows(): array;

    /**
     * Reporters the sheet names but the referents lack (RequestSheetLookups::ensureReferents()).
     *
     * @return array<string, array{0: string, 1: string}|null>
     */
    protected function newReporters(): array
    {
        return [];
    }

    /**
     * Commercials the sheet names but the referents lack, same shape as newReporters().
     *
     * @return array<string, array{0: string, 1: string}|null>
     */
    protected function newCommercials(): array
    {
        return [];
    }

    /**
     * Informazioni aggiuntive written from the rows: attribute code => row key.
     *
     * @return array<string, string>
     */
    protected function attributeColumns(): array
    {
        return [];
    }

    /** Catalogue changes the rows depend on, run before them. */
    protected function prepare(ProductCategory $category): void {}

    public function run(): void
    {
        // Step 1: who writes, on which category (a row may name its own).
        $actor = $this->resolveActor();
        $category = $this->lookups->category($this->categoryName());

        // Step 2: what the rows point to.
        $this->prepare($category);
        $this->lookups->ensureReferents($actor, $this->newReporters(), self::REPORTER_TYPE);
        $this->lookups->ensureReferents($actor, $this->newCommercials(), self::COMMERCIAL_TYPE);

        // Step 3: one request per row, silently.
        $outcomes = AssignmentNotifier::muted(fn (): array => $this->writeRows($actor, $category));

        // Step 4: the summary.
        $this->command?->info(sprintf(
            '%s: %d created, %d added to an open opportunity, %d already present, %d refused.',
            $this->label(),
            $outcomes[RequestSheetWriter::CREATED],
            $outcomes[RequestSheetWriter::APPENDED],
            $outcomes[RequestSheetWriter::ALREADY_PRESENT],
            count($outcomes['refused']),
        ));

        foreach ($outcomes['refused'] as $line) {
            $this->command?->warn($line);
        }
    }

    /**
     * @return array{created: int, appended: int, already_present: int, refused: list<string>}
     */
    private function writeRows(User $actor, ProductCategory $category): array
    {
        $outcomes = [RequestSheetWriter::CREATED => 0, RequestSheetWriter::APPENDED => 0, RequestSheetWriter::ALREADY_PRESENT => 0, 'refused' => []];

        foreach ($this->rows() as $row) {
            try {
                $rowCategory = isset($row['category']) ? $this->lookups->category($row['category']) : $category;
                $outcomes[$this->writer->write($actor, $rowCategory, $row, $this->attributeColumns())]++;
            } catch (ValidationException $exception) {
                $outcomes['refused'][] = sprintf('%s %s (%s): %s', $row['first'] ?? '', $row['last'] ?? $row['company'] ?? '', $row['phones'][0] ?? '-', $exception->getMessage());
            }
        }

        return $outcomes;
    }
}
