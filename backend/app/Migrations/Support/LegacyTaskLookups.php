<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\WorkOrderStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Name -> id resolution of the task lookups for the `tasks` migration source
 * (spec 0289, D-5/D-6): the legacy sends names, qnet-2 owns the rows. Each
 * table is read once per instance (the lookups are a handful of rows, the
 * source resolves them for every one of tens of thousands of tasks), matched
 * by trimmed, case-insensitive name; the first row by id wins on a duplicate.
 * A work order's stages are read once per commessa, matched the same way.
 */
final class LegacyTaskLookups
{
    /** @var array<string, array<string, int>> */
    private array $idsByName = [];

    /** @var array<string, int> */
    private array $statusIdsBySystemKey = [];

    /** @var array<int, array<string, int>> */
    private array $stageIdsByWorkOrder = [];

    public function statusIdBySystemKey(string $systemKey): ?int
    {
        if ($this->statusIdsBySystemKey === []) {
            $this->statusIdsBySystemKey = TaskStatus::query()
                ->whereNotNull('system_key')
                ->orderBy('id')
                ->get(['id', 'system_key'])
                ->mapWithKeys(fn (TaskStatus $status): array => [$status->system_key->value => $status->id])
                ->all();
        }

        return $this->statusIdsBySystemKey[$systemKey] ?? null;
    }

    public function statusIdByName(string $name): ?int
    {
        return $this->idByName(TaskStatus::class, $name);
    }

    public function typeIdByName(string $name): ?int
    {
        return $this->idByName(TaskType::class, $name);
    }

    public function categoryIdByName(string $name): ?int
    {
        return $this->idByName(TaskCategory::class, $name);
    }

    public function priorityIdByName(string $name): ?int
    {
        return $this->idByName(TaskPriority::class, $name);
    }

    public function importanceIdByName(string $name): ?int
    {
        return $this->idByName(TaskImportance::class, $name);
    }

    public function stageIdByName(int $workOrderId, string $name): ?int
    {
        if (! isset($this->stageIdsByWorkOrder[$workOrderId])) {
            $this->stageIdsByWorkOrder[$workOrderId] = $this->indexByName(
                WorkOrderStage::query()->where('work_order_id', $workOrderId)->orderBy('sort_order')->orderBy('id'),
            );
        }

        return $this->stageIdsByWorkOrder[$workOrderId][$this->normalize($name)] ?? null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function idByName(string $modelClass, string $name): ?int
    {
        $this->idsByName[$modelClass] ??= $this->indexByName($modelClass::query()->orderBy('id'));

        return $this->idsByName[$modelClass][$this->normalize($name)] ?? null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    private function indexByName(Builder $query): array
    {
        $index = [];

        foreach ($query->get(['id', 'name']) as $row) {
            $index[$this->normalize((string) $row->name)] ??= (int) $row->id;
        }

        return $index;
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
