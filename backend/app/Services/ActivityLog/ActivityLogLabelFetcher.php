<?php

namespace App\Services\ActivityLog;

use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

/**
 * Fetches the human-readable label of every referenced id, one query per
 * related model class (see ForeignKeyLabelResolver, which decides WHICH ids
 * of WHICH class a page references).
 *
 * A class owning a plain label column reads it with a single `pluck`; a
 * class with no identity column of its own (OperationalSite, Lead) composes
 * its label exactly like its *ForSelectResource does, from an eager-loaded
 * relation. An id with no usable label is simply absent from the result, so
 * the frontend falls back to the raw value.
 */
final class ActivityLogLabelFetcher
{
    /**
     * Exceptions to the default `name` column, verified against each model's
     * real schema/*ForSelectResource (ADR 0011).
     *
     * @var array<class-string<Model>, string>
     */
    private const array LABEL_COLUMNS = [
        Company::class => 'denomination',
        CustomFieldDefinition::class => 'label',
        Quote::class => 'code',
        WorkOrder::class => 'code',
        Task::class => 'title',
    ];

    /** @var array<class-string<Model>, string|null> memo of related class => label column, for this request */
    private array $labelColumnCache = [];

    /**
     * @param  array<class-string<Model>, array<int, int>>  $idsByClass
     * @return array<class-string<Model>, array<int, string>>
     */
    public function fetch(array $idsByClass): array
    {
        $labels = [];

        foreach ($idsByClass as $class => $ids) {
            $labels[$class] = array_filter(
                $this->labelsOf($class, $ids),
                static fn (?string $label): bool => $label !== null && $label !== '',
            );
        }

        return $labels;
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<int, int>  $ids
     * @return array<int, string|null>
     */
    private function labelsOf(string $class, array $ids): array
    {
        return match ($class) {
            OperationalSite::class => $this->query($class, $ids)
                ->with('addresses.city')
                ->get()
                ->mapWithKeys(static fn (OperationalSite $site): array => [
                    $site->id => OperationalSiteLabel::compose($site->primaryAddress) ?: $site->alias,
                ])
                ->all(),
            Lead::class => $this->query($class, $ids)
                ->with('registry')
                ->get()
                ->mapWithKeys(static fn (Lead $lead): array => [$lead->id => $lead->registry?->name])
                ->all(),
            default => $this->pluckedLabelsOf($class, $ids),
        };
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<int, int>  $ids
     * @return array<int, string|null>
     */
    private function pluckedLabelsOf(string $class, array $ids): array
    {
        $column = $this->labelColumnFor($class);

        if ($column === null) {
            return [];
        }

        /** @var array<int, string|null> $rows */
        $rows = $this->query($class, $ids)->pluck($column, 'id')->all();

        return $rows;
    }

    /**
     * @param  class-string<Model>  $class
     * @param  array<int, int>  $ids
     * @return Builder<Model>
     */
    private function query(string $class, array $ids): Builder
    {
        $query = $class::query();

        // Record correlato cancellato (soft-delete): risolvilo comunque,
        // cosi' l'entry storica resta leggibile invece di finire null.
        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        return $query->whereIn('id', $ids);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function labelColumnFor(string $class): ?string
    {
        if (array_key_exists($class, $this->labelColumnCache)) {
            return $this->labelColumnCache[$class];
        }

        if (isset(self::LABEL_COLUMNS[$class])) {
            return $this->labelColumnCache[$class] = self::LABEL_COLUMNS[$class];
        }

        $hasName = Schema::hasColumn((new $class)->getTable(), 'name');

        return $this->labelColumnCache[$class] = $hasName ? 'name' : null;
    }
}
