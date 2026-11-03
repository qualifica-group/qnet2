<?php

namespace Database\Seeders\QualificaCatalog;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Models\Referent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Makes a reporter's commission rules converge on the catalogue: one rule per
 * target, created when missing, updated when it differs (an edit made from
 * the UI is overwritten: the catalogue is the source of truth for these
 * reporters, user directive 2026-11-03). A rule the seeder created
 * (name prefixed RULE_NAME_PREFIX) that the catalogue no longer lists is
 * removed; a rule added by hand under another name is left alone.
 */
final class ReporterCommissionRules
{
    private const int NAME_LENGTH = 191;

    /**
     * @param  array<string, string>  $rates
     * @param  array<string, list<array{scope: CommissionApplicationScope, id: int, label: string}>>  $targets
     * @return array{created: int, updated: int, removed: int}
     */
    public function sync(Referent $referent, array $rates, array $targets, ?string $reporterNote): array
    {
        // Step 1: the rules the catalogue wants, keyed like the existing ones.
        $desired = $this->desired($referent, $rates, $targets, $reporterNote);

        // Step 2: create or update each of them.
        $existing = $this->ruleQuery($referent)->get()->groupBy(fn (CommissionConfiguration $rule) => $this->ruleKey($rule));
        $counts = ['created' => 0, 'updated' => 0, 'removed' => 0];

        foreach ($desired as $key => $data) {
            $rule = $this->claim($existing, $key);

            if ($rule === null) {
                CommissionConfiguration::query()->create($data->attributes());
                $counts['created']++;

                continue;
            }

            $rule->fill($data->attributes());

            if ($rule->isDirty()) {
                $rule->save();
                $counts['updated']++;
            }
        }

        // Step 3: drop the seeder's rules the catalogue no longer lists.
        foreach ($existing->collapse() as $rule) {
            if ($this->isSeeded($rule)) {
                $counts['removed'] += (int) $this->remove($rule);
            }
        }

        return $counts;
    }

    /**
     * The seeder's rules whose referent no longer exists (deleted from the UI):
     * their reporter is recreated with a new id, so they would never be reused.
     */
    public function removeOrphans(): int
    {
        $removed = 0;

        CommissionConfiguration::query()
            ->where('recipient_role', CommissionRecipientRole::Reporter)
            ->where('recipient_type', (new Referent)->getMorphClass())
            ->where('name', 'like', self::seededPrefix().'%')
            ->whereNotIn('recipient_id', Referent::query()->select('id'))
            ->each(function (CommissionConfiguration $rule) use (&$removed): void {
                $removed += (int) $this->remove($rule);
            });

        return $removed;
    }

    /**
     * @param  array<string, string>  $rates
     * @param  array<string, list<array{scope: CommissionApplicationScope, id: int, label: string}>>  $targets
     * @return array<string, CreateCommissionConfigurationData>
     */
    private function desired(Referent $referent, array $rates, array $targets, ?string $reporterNote): array
    {
        $desired = [];

        foreach ($rates as $column => $value) {
            $definition = ReporterCommissionCatalogue::COLUMNS[$column];
            $note = implode(' ', array_filter([$definition['note'] ?? null, $reporterNote])) ?: null;

            foreach ($targets[$column] ?? [] as $target) {
                $isProduct = $target['scope'] === CommissionApplicationScope::Product;

                $desired[$this->key($target['scope'], $target['id'])] = new CreateCommissionConfigurationData(
                    name: Str::substr(sprintf('%s - %s - %s', ReporterCommissionCatalogue::RULE_NAME_PREFIX, $referent->name, $target['label']), 0, self::NAME_LENGTH),
                    recipientRole: CommissionRecipientRole::Reporter,
                    applicationScope: $target['scope'],
                    productCategoryId: $isProduct ? null : $target['id'],
                    productId: $isProduct ? $target['id'] : null,
                    recipientId: $referent->id,
                    recipientType: $referent->getMorphClass(),
                    commissionType: CommissionType::Percentage,
                    value: $value,
                    priority: 0,
                    validFrom: $definition['valid_from'] ?? ReporterCommissionCatalogue::VALID_FROM,
                    validUntil: null,
                    status: CommissionConfigurationStatus::Active,
                    internalNote: $note,
                );
            }
        }

        return $desired;
    }

    /**
     * A rule already applied to a quote line cannot be deleted (restrict FK):
     * it is suspended instead, so it stops matching. False when nothing changed.
     */
    private function remove(CommissionConfiguration $rule): bool
    {
        if (! $rule->appliedCommissions()->exists()) {
            return (bool) $rule->delete();
        }

        if ($rule->status === CommissionConfigurationStatus::Suspended) {
            return false;
        }

        return $rule->update(['status' => CommissionConfigurationStatus::Suspended]);
    }

    /**
     * @return Builder<CommissionConfiguration>
     */
    private function ruleQuery(Referent $referent): Builder
    {
        return CommissionConfiguration::query()
            ->where('recipient_role', CommissionRecipientRole::Reporter)
            ->whereIn('application_scope', [CommissionApplicationScope::Product, CommissionApplicationScope::ProductCategory])
            ->where('recipient_type', $referent->getMorphClass())
            ->where('recipient_id', $referent->id);
    }

    /**
     * Takes the existing rule for $key out of $existing: the seeder's own when
     * there are several, else the first one (adopted and renamed).
     *
     * @param  Collection<string, Collection<int, CommissionConfiguration>>  $existing
     */
    private function claim(Collection $existing, string $key): ?CommissionConfiguration
    {
        $group = $existing->get($key);

        if ($group === null || $group->isEmpty()) {
            return null;
        }

        $index = $group->search(fn (CommissionConfiguration $rule) => $this->isSeeded($rule));

        return $group->pull($index === false ? $group->keys()->first() : $index);
    }

    private function isSeeded(CommissionConfiguration $rule): bool
    {
        return str_starts_with($rule->name, self::seededPrefix());
    }

    private static function seededPrefix(): string
    {
        return ReporterCommissionCatalogue::RULE_NAME_PREFIX.' - ';
    }

    private function ruleKey(CommissionConfiguration $rule): string
    {
        return $this->key($rule->application_scope, $rule->product_id ?? $rule->product_category_id);
    }

    private function key(CommissionApplicationScope $scope, ?int $id): string
    {
        return $scope->value.':'.$id;
    }
}
