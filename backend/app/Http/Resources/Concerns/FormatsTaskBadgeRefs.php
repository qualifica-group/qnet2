<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The five field-shaping helpers TaskResource (spec 0101) and
 * BoardTaskResource (spec 0146) render identically — the data_contract says
 * so explicitly for the badge/status pair ("i campi badge usano la stessa
 * forma di TaskResource::badgeRef()/statusRef()"), and the other three
 * (`nameRef`/`summarizeUsers`/`formatDate`) are the exact same one-liners
 * both already needed; a second hand-written copy of any of them is the
 * naming/shape drift `engineering.md §1.1` warns against, not genuine
 * per-resource variation.
 *
 * All five read `$this->...` through the underlying JsonResource's magic
 * proxy to the wrapped Task, so this trait only works `use`d inside a
 * JsonResource whose resource is a Task.
 */
trait FormatsTaskBadgeRefs
{
    /**
     * The Task's own status, with the two attributes the badge needs plus the
     * two the client drives behaviour off: the phase key (never the label)
     * and the percentage the whole module derives from it.
     *
     * @return array<string, mixed>|null
     */
    private function statusRef(): ?array
    {
        $status = $this->taskStatus;

        if ($status === null) {
            return null;
        }

        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'icon' => $status->icon,
            'system_key' => $status->system_key?->value,
            'group' => $status->group->value,
            'completion_percentage' => $status->completion_percentage,
        ];
    }

    /**
     * A lookup configurator row projected with its badge attributes, so the
     * grid and the detail render the SAME configured colour/icon (AC-072).
     *
     * @return array{id: int, name: string, color: string|null, icon: string|null}|null
     */
    private function badgeRef(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
            'color' => $related->color,
            'icon' => $related->icon,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function nameRef(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeUsers(Collection $users): array
    {
        return $users->map(static fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
        ])->all();
    }

    /**
     * `Y-m-d`, the shape the data_contract declares and the shape an
     * `<input type="date">` accepts. The `date:Y-m-d` cast alone is not
     * enough: it governs the MODEL's serialization, while a Resource hands
     * the raw CarbonImmutable to json_encode, which renders a full ISO-8601
     * timestamp (the bug spec 0096 found on WorkOrderResource).
     */
    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}
