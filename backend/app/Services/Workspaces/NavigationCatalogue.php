<?php

namespace App\Services\Workspaces;

/**
 * Flat view of the GLOBAL (unfiltered) navigation config: every item with a
 * route, in menu order (spec 0317). Single source for catalogue endpoint,
 * key validation and key ordering.
 */
class NavigationCatalogue
{
    /**
     * @return array<int, array{key: string, label: string, icon: string|null, route: string, path: array<int, string>}>
     */
    public function entries(): array
    {
        return $this->flatten(config('navigation.items', []), []);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_column($this->entries(), 'key');
    }

    /**
     * Keep only the keys still in config, in global menu order.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public function orderKeys(array $keys): array
    {
        return array_values(array_intersect($this->keys(), $keys));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $path
     * @return array<int, array{key: string, label: string, icon: string|null, route: string, path: array<int, string>}>
     */
    private function flatten(array $items, array $path): array
    {
        $entries = [];

        foreach ($items as $item) {
            if (! empty($item['route'])) {
                $entries[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'route' => $item['route'],
                    'path' => $path,
                ];
            }

            $entries = array_merge($entries, $this->flatten($item['children'] ?? [], [...$path, $item['label']]));
        }

        return $entries;
    }
}
