<?php

declare(strict_types=1);

namespace Integrations\Formalab\Seeders;

use App\Migrations\Support\ExternalApiClient;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Integrations\Formalab\Models\FormalabLearnerCommission;
use Integrations\Formalab\Seeders\Support\UserReferentResolver;
use Integrations\Formalab\Services\LearnerStatement\CommissionBook;

/**
 * One-shot of spec 0427 D-8, after the opportunities, quotes and registries
 * migration: `php artisan db:seed --class="Integrations\Formalab\Seeders\LearnerCommercialPartnerSeeder" --force`.
 *
 * Reads the learners' partner data from the legacy (`learner-commercial-partners`):
 * when the hand-written partner is the G.A.3 of the opportunity, the G.A.3's
 * referent (found or created) becomes the "Partner commerciale" of the
 * opportunity and of its quotes; the commissions (%, amount, supplier) go to
 * the learner statement by tax code. Only empty values are filled, so what was
 * set in qnet-2 wins and a second run changes nothing. The partner is written
 * without activity log, `updated_at` or quote commission resync, like the import.
 */
final class LearnerCommercialPartnerSeeder extends Seeder
{
    public const string ENDPOINT = 'learner-commercial-partners';

    public const int PAGE_SIZE = 100;

    /** @var array<string, int> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $totals = ['opportunities' => 0, 'quotes' => 0, 'commissions' => 0];

    public function __construct(
        private readonly ExternalApiClient $client,
        private readonly UserReferentResolver $referents,
    ) {}

    public function run(): void
    {
        $actor = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserService::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            $this->command?->warn('Partner commerciali discenti saltati: nessun super-admin con cui creare i referenti.');

            return;
        }

        $offset = 0;

        do {
            // Step 1: one page of learners from the legacy
            $payload = $this->client->get(self::ENDPOINT, ['offset' => $offset, 'limit' => self::PAGE_SIZE]);
            $items = array_values(array_filter((array) ($payload['items'] ?? []), is_array(...)));

            // Step 2: the legacy's reasons for a partner left out
            foreach (array_merge(...array_map(static fn (array $item): array => (array) ($item['warnings'] ?? []), $items)) as $warning) {
                $this->warn((string) $warning);
            }

            // Step 3: the partner on the opportunities and quotes, the commissions by tax code
            $this->assignPartners($items, $actor);
            $this->storeCommissions($items);

            $offset += self::PAGE_SIZE;
        } while ($items !== [] && $offset < (int) ($payload['pagination']['total'] ?? 0));

        $this->report();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function assignPartners(array $items, User $actor): void
    {
        $items = array_values(array_filter($items, static fn (array $item): bool => isset($item['commercial_partner_user_id'])));
        $opportunityIds = $this->idsByOldId(Opportunity::class, array_column($items, 'opportunity_id'));
        $users = User::query()->whereIn('old_id', array_column($items, 'commercial_partner_user_id'))->get()->keyBy('old_id');

        $opportunitiesByReferent = [];
        foreach ($items as $item) {
            $opportunityId = $opportunityIds[(int) $item['opportunity_id']] ?? null;
            $user = $users->get((int) $item['commercial_partner_user_id']);

            if ($opportunityId === null || $user === null) {
                $this->warn($opportunityId === null
                    ? sprintf('Opportunita\' legacy %d non migrata.', $item['opportunity_id'])
                    : sprintf('Utente legacy %d non migrato.', $item['commercial_partner_user_id']));

                continue;
            }

            $opportunitiesByReferent[$this->referents->resolve($user, $actor, $this->warn(...))->id][] = $opportunityId;
        }

        foreach ($opportunitiesByReferent as $referentId => $ids) {
            $this->totals['opportunities'] += Opportunity::query()->whereKey($ids)->whereNull('commercial_partner_id')
                ->toBase()->update(['commercial_partner_id' => $referentId]);
            $this->totals['quotes'] += Quote::query()->whereIn('opportunity_id', $ids)->whereNull('commercial_partner_id')
                ->toBase()->update(['commercial_partner_id' => $referentId]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function storeCommissions(array $items): void
    {
        $commissions = [];
        foreach ($items as $item) {
            if (is_array($item['commission'] ?? null) && is_string($item['tax_code'] ?? null)) {
                $commissions[CommissionBook::normalize($item['tax_code'])] = $item['commission'];
            }
        }

        $rows = FormalabLearnerCommission::query()->whereIn('tax_code', array_keys($commissions))->get()->keyBy('tax_code');
        $suppliers = $this->idsByOldId(Registry::class, array_column($commissions, 'supplier_registry_id'));

        foreach ($commissions as $taxCode => $commission) {
            $row = $rows->get($taxCode) ?? new FormalabLearnerCommission(['tax_code' => $taxCode]);

            foreach ($this->commissionValues($commission, $suppliers) as $column => $value) {
                if ($row->getAttribute($column) === null && $value !== null) {
                    $row->setAttribute($column, $value);
                }
            }

            if ($row->isDirty()) {
                $row->save();
                $this->totals['commissions']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $commission
     * @param  array<int, int>  $suppliers  legacy company id => registry id
     * @return array{percentage: ?float, amount: ?float, supplier_registry_id: ?int}
     */
    private function commissionValues(array $commission, array $suppliers): array
    {
        $percentage = is_numeric($commission['percentage'] ?? null) ? (float) $commission['percentage'] : null;
        $amount = is_numeric($commission['amount'] ?? null) ? (float) $commission['amount'] : null;
        $supplierId = isset($commission['supplier_registry_id']) ? (int) $commission['supplier_registry_id'] : null;

        if ($percentage !== null && ($percentage < 0 || $percentage > 100)) {
            $this->warn(sprintf('Percentuale %s fuori da 0-100, ignorata.', $percentage));
            $percentage = null;
        }

        if ($amount !== null && $amount < 0) {
            $this->warn(sprintf('Importo %s negativo, ignorato.', $amount));
            $amount = null;
        }

        if ($supplierId !== null && ! isset($suppliers[$supplierId])) {
            $this->warn(sprintf('Fornitore legacy %d non migrato.', $supplierId));
        }

        return [
            'percentage' => $percentage,
            'amount' => $amount,
            'supplier_registry_id' => $supplierId === null ? null : ($suppliers[$supplierId] ?? null),
        ];
    }

    /**
     * @param  class-string<Opportunity|Registry>  $model
     * @param  array<int, mixed>  $oldIds
     * @return array<int, int> old id => id
     */
    private function idsByOldId(string $model, array $oldIds): array
    {
        $oldIds = array_values(array_unique(array_filter(array_map(intval(...), $oldIds))));

        return $oldIds === [] ? [] : $model::query()->whereIn('old_id', $oldIds)->toBase()->pluck('id', 'old_id')
            ->mapWithKeys(static fn ($id, $oldId): array => [(int) $oldId => (int) $id])->all();
    }

    private function warn(string $message): void
    {
        $this->warnings[$message] = ($this->warnings[$message] ?? 0) + 1;
    }

    private function report(): void
    {
        $this->command?->info(sprintf(
            'Partner commerciali discenti: %d referenti creati, %d opportunita\' e %d preventivi con partner, %d provvigioni scritte.',
            $this->referents->createdCount(), $this->totals['opportunities'], $this->totals['quotes'], $this->totals['commissions'],
        ));

        arsort($this->warnings);
        foreach ($this->warnings as $message => $count) {
            $this->command?->warn(sprintf('%5d x %s', $count, $message));
        }
    }
}
