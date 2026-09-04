<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * For-select projection of a Task (GET /api/tasks/for-select, ADR 0011, spec
 * 0101): `label` is the title and `subtitle` the current status name, so the
 * parent-task picker distinguishes two same-named tasks at a glance.
 *
 * `meta` is emitted as an EMPTY OBJECT, exactly as the frozen data_contract
 * declares it: an empty PHP array would serialize as `[]` and change the
 * shape the client parses. Nothing else belongs here — `meta` is a small,
 * flat presentation bag, not an escape hatch for entity data, and the rows
 * this endpoint returns are already restricted by TaskVisibilityScope.
 *
 * @mixin Task
 */
class TaskForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->title,
            'subtitle' => $this->taskStatus?->name,
            'meta' => (object) [],
        ];
    }
}
