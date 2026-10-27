<?php

namespace App\Services\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    /**
     * @param  array<string, mixed>  $data  validated payload, `navigation_keys` included
     */
    public function create(array $data): Workspace
    {
        return DB::transaction(function () use ($data): Workspace {
            $workspace = Workspace::query()->create($this->attributes($data));
            $this->replaceKeys($workspace, $data['navigation_keys']);

            return $workspace->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, array $data): Workspace
    {
        return DB::transaction(function () use ($workspace, $data): Workspace {
            $workspace->update($this->attributes($data));
            $changed = $this->replaceKeys($workspace, $data['navigation_keys']);

            // A keys-only edit leaves the row clean, so it would not be audited.
            if ($changed && ! $workspace->wasChanged()) {
                activity('workspaces')->performedOn($workspace)->event('updated')
                    ->withProperties(['navigation_keys' => $data['navigation_keys']])->log('updated');
            }

            return $workspace->load('items');
        });
    }

    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * @param  array<int, string>  $keys
     * @return bool whether the persisted set changed
     */
    private function replaceKeys(Workspace $workspace, array $keys): bool
    {
        $current = $workspace->items()->pluck('navigation_key')->all();
        $removed = array_diff($current, $keys);
        $added = array_diff($keys, $current);

        $workspace->items()->whereIn('navigation_key', $removed)->delete();
        foreach ($added as $key) {
            $workspace->items()->create(['navigation_key' => $key]);
        }

        return $removed !== [] || $added !== [];
    }
}
