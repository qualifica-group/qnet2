<?php

namespace Database\Seeders;

use App\Models\OperationalSite;
use App\Models\Source;
use App\Models\User;
use App\Services\Concerns\GeneratesSequentialCode;
use App\Services\Quotes\RevenueProductTitleBuilder;
use App\Services\QuoteService;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Database\Seeders\Support\BulkRegistryRows;
use Database\Seeders\Support\SampleRequestTemplate;
use Database\Seeders\Support\SampleRequestTemplates;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * VOLUME for the Gestione Richieste grid (user directive 2026-09-29: a million
 * requests to test the application on): `php artisan qualifica:seed-sample
 * --bulk --requests=1000000`.
 *
 * QualificaSampleRequestSeeder creates one request per ~60 queries through
 * RequestCreationService — about 33 hours for a million. This seeder writes
 * them with multi-row INSERTs instead, CHUNK requests per transaction, each on
 * an Anagrafica of its own (registry, personal-data card, primary email and
 * phone — the stack the grid reads): an anagrafica carries ONE open
 * opportunity at a time (user directive 2026-08-31).
 *
 * The rows are COPIES, not a second write path: a set of templates is first
 * created through the real one and rolled back (SampleRequestTemplates), and
 * every bulk request copies one of them — workflow status, layout, product
 * lines, offer row with its frozen amounts, team. What a copy changes is what
 * makes it a row of its own: the Anagrafica, the ids, the sequential `code`
 * and the automatic title and name composed from it (RevenueProductTitleBuilder),
 * and — rotated like the request seeder rotates them — the team, the Fonte and
 * the Sede. The creation date is spread over the last CREATED_WITHIN_DAYS so
 * date sorting and filtering have something to work on.
 *
 * What a copy deliberately lacks: activity-log entries and in-app
 * notifications. A million of each would dwarf the rows under test, and
 * neither is read by the grid.
 *
 * Ids are assigned here, from each table's MAX(id), so the rows of a chunk can
 * reference each other without reading them back: run it on a database
 * nobody is writing to at the same time.
 */
class QualificaSampleBulkRequestSeeder extends Seeder
{
    use GeneratesSequentialCode;
    use SeedsWithoutMail;

    /** The batch size when the caller names none. */
    public const int DEFAULT_REQUESTS = 1000;

    /** Requests per transaction: every table of a chunk is one INSERT within the driver's placeholder limit. */
    private const int CHUNK = 500;

    /** Templates to copy: QualificaSampleRequestSeeder rotates them over the offers, one per offer. */
    private const int MAX_TEMPLATES = 150;

    private const int CREATED_WITHIN_DAYS = 365;

    /**
     * Seeded times fall within working hours: a random one could land in a
     * daylight-saving gap ("02:30" on the spring-forward day), which MySQL
     * rejects on a TIMESTAMP column read in a DST zone.
     */
    private const int FIRST_WORKING_HOUR = 8;

    private const int LAST_WORKING_HOUR = 19;

    /** The sequence of the next `quotes.code`, read once: nobody else writes while this runs. */
    private int $codeSequence = 1;

    public function __construct(
        private readonly SampleRequestTemplates $templates,
        private readonly BulkRegistryRows $registries,
        private readonly RevenueProductTitleBuilder $titles,
    ) {}

    public function run(int $requests = self::DEFAULT_REQUESTS): void
    {
        $this->withoutMail(fn () => $this->seed($requests));
    }

    private function seed(int $requests): void
    {
        // Step 1: the shapes to copy, created and rolled back.
        $templates = $this->templates->capture($this->container, min($requests, self::MAX_TEMPLATES));

        if ($templates === []) {
            $this->command?->warn('Bulk sample requests skipped: the request seeder could not create a template (see QualificaSampleRequestSeeder).');

            return;
        }

        // Step 2: what every copy rotates over.
        $faker = FakerFactory::create('it_IT');
        $lookups = [
            'sources' => Source::query()->orderBy('id')->pluck('id')->all(),
            'sites' => OperationalSite::query()->orderBy('id')->pluck('id')->all(),
            'managers' => User::query()->orderBy('id')->pluck('id')->all(),
        ];
        $nextCode = $this->peekNextSequentialCode('quotes', 'code', QuoteService::CODE_PREFIX);
        $this->codeSequence = (int) substr($nextCode, strlen(QuoteService::CODE_PREFIX) + 1);

        // Step 3: the batch, one transaction per chunk.
        $progress = $this->command?->getOutput()->createProgressBar($requests);

        for ($offset = 0; $offset < $requests; $offset += self::CHUNK) {
            $size = min(self::CHUNK, $requests - $offset);
            DB::transaction(fn () => $this->insertChunk($faker, $templates, $lookups, $offset, $size));
            $progress?->advance($size);
        }

        $progress?->finish();
        $this->command?->newLine();
        $this->command?->info(sprintf('%d bulk sample requests seeded from %d templates.', $requests, count($templates)));
    }

    /**
     * @param  list<SampleRequestTemplate>  $templates
     * @param  array{sources: list<int>, sites: list<int>, managers: list<int>}  $lookups
     */
    private function insertChunk(Generator $faker, array $templates, array $lookups, int $offset, int $size): void
    {
        $ids = [];

        foreach (['registries', 'personal_data', 'opportunities', 'quotes', 'quote_lines'] as $table) {
            $ids[$table] = (int) DB::table($table)->max('id') + 1;
        }

        $rows = [];

        for ($index = $offset; $index < $offset + $size; $index++) {
            $createdAt = $this->workingHourTimestamp($faker, '-'.self::CREATED_WITHIN_DAYS.' days', '-1 day');
            $registryId = $ids['registries']++;
            $this->registries->add($rows, $index, $registryId, $ids['personal_data']++, $this->pick($lookups['sources'], $index), ['created_at' => $createdAt, 'updated_at' => $createdAt]);
            $this->addRequest($rows, $ids, $faker, $templates[$index % count($templates)], $index, $registryId, $createdAt, $lookups);
        }

        // Parents before children: the foreign keys are checked row by row.
        foreach ($rows as $table => $tableRows) {
            DB::table($table)->insert($tableRows);
        }
    }

    /**
     * The Opportunity, its Offerta and their team and lines, copied from
     * $template onto the new Anagrafica.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows
     * @param  array<string, int>  $ids
     * @param  array{sources: list<int>, sites: list<int>, managers: list<int>}  $lookups
     */
    private function addRequest(array &$rows, array &$ids, Generator $faker, SampleRequestTemplate $template, int $index, int $registryId, string $createdAt, array $lookups): void
    {
        $opportunityId = $ids['opportunities']++;
        $quoteId = $ids['quotes']++;
        $code = $this->formatSequentialCode(QuoteService::CODE_PREFIX, $this->codeSequence++);
        $siteId = $this->pick($lookups['sites'], $index);
        $users = $this->rotatedManagers($template, $lookups['managers'], $index);
        $timestamps = ['created_at' => $createdAt, 'updated_at' => $createdAt];

        $rows['opportunities'][] = [
            ...$template->opportunity,
            'id' => $opportunityId,
            'name' => $this->titles->compose($this->titles->opportunityCode($opportunityId), $template->productNames),
            'registry_id' => $registryId,
            'source_id' => $this->pick($lookups['sources'], $index),
            'operational_site_id' => $siteId,
            'general_notes' => $faker->boolean(60) ? $faker->sentence(12) : null,
            ...$timestamps,
        ];

        foreach ($template->productLines as $line) {
            $rows['opportunity_product_lines'][] = [...$this->withoutId($line), 'opportunity_id' => $opportunityId, ...$timestamps];
        }

        foreach ($template->opportunityManagers as $manager) {
            $rows['opportunity_user'][] = [...$this->withoutId($manager), 'opportunity_id' => $opportunityId, 'user_id' => $users[$manager['user_id']] ?? $manager['user_id']];
        }

        $rows['quotes'][] = [
            ...$template->quote,
            'id' => $quoteId,
            'code' => $code,
            'title' => $this->titles->compose($code, $template->productNames),
            'opportunity_id' => $opportunityId,
            'operator_id' => $users[$template->quote['operator_id']] ?? $template->quote['operator_id'],
            'operational_site_id' => $siteId,
            'next_callback_at' => $faker->boolean(50) ? $this->workingHourTimestamp($faker, '+1 day', '+2 months') : null,
            ...$timestamps,
        ];

        foreach ($template->quoteManagers as $manager) {
            $rows['quote_user'][] = [...$this->withoutId($manager), 'quote_id' => $quoteId, 'user_id' => $users[$manager['user_id']] ?? $manager['user_id']];
        }

        $this->addLines($rows, $ids, $template, $quoteId, $timestamps);
    }

    /**
     * The Offerta's lines and their commissions, with the template's own line
     * ids (a COST row's `offer_line_id`, a commission's `quote_line_id`)
     * remapped onto the copies.
     *
     * @param  array<string, list<array<string, mixed>>>  $rows
     * @param  array<string, int>  $ids
     * @param  array{created_at: string, updated_at: string}  $timestamps
     */
    private function addLines(array &$rows, array &$ids, SampleRequestTemplate $template, int $quoteId, array $timestamps): void
    {
        $lineIds = [];

        foreach ($template->lines as $line) {
            $lineIds[$line['id']] = $ids['quote_lines']++;
        }

        foreach ($template->lines as $line) {
            $rows['quote_lines'][] = [
                ...$line,
                'id' => $lineIds[$line['id']],
                'quote_id' => $quoteId,
                'offer_line_id' => $line['offer_line_id'] === null ? null : $lineIds[$line['offer_line_id']],
                ...$timestamps,
            ];
        }

        foreach ($template->commissions as $commission) {
            $rows['quote_line_commissions'][] = [...$this->withoutId($commission), 'quote_line_id' => $lineIds[$commission['quote_line_id']], ...$timestamps];
        }
    }

    /**
     * Template manager id => this copy's, rotated by $index over the roster
     * the way QualificaSampleRequestSeeder rotates its team: slot k takes the
     * (index + k)-th account, so the slots stay distinct.
     *
     * @param  list<int>  $managers
     * @return array<int, int>
     */
    private function rotatedManagers(SampleRequestTemplate $template, array $managers, int $index): array
    {
        $users = [];

        foreach ($template->quoteManagers as $slot => $manager) {
            $users[$manager['user_id']] = $managers[($index + $slot) % count($managers)];
        }

        return $users;
    }

    private function workingHourTimestamp(Generator $faker, string $from, string $to): string
    {
        return sprintf(
            '%s %02d:%02d:%02d',
            $faker->dateTimeBetween($from, $to)->format('Y-m-d'),
            $faker->numberBetween(self::FIRST_WORKING_HOUR, self::LAST_WORKING_HOUR),
            $faker->numberBetween(0, 59),
            $faker->numberBetween(0, 59),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withoutId(array $row): array
    {
        unset($row['id']);

        return $row;
    }

    /**
     * @param  list<int>  $ids
     */
    private function pick(array $ids, int $index): ?int
    {
        return $ids === [] ? null : $ids[$index % count($ids)];
    }
}
