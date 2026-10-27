<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Services\NavigationService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Which workspaces a user can enter and what each one shows (spec 0317).
 * Visibility is delegated to NavigationService: a workspace is visible when
 * its pruned menu is not empty.
 */
class WorkspaceAccessService
{
    public function __construct(private readonly NavigationService $navigation) {}

    /**
     * @return array{workspaces: array<int, array{slug: string, name: string, description: string|null, icon: string|null, color: string|null, home_route: string}>, full_view_available: bool}
     */
    public function available(User $user): array
    {
        // Step 1: the user's visible menu, computed once for all workspaces
        $tree = $this->navigation->for($user);

        // Step 2: keep the workspaces whose pruned menu is not empty
        $workspaces = [];
        foreach ($this->all() as $workspace) {
            $keys = $workspace->navigationKeys();
            $home = $this->homeRoute($this->navigation->prune($tree, $keys), $keys);

            if ($home !== null) {
                $workspaces[] = [
                    'slug' => $workspace->slug,
                    'name' => $workspace->name,
                    'description' => $workspace->description,
                    'icon' => $workspace->icon,
                    'color' => $workspace->color,
                    'home_route' => $home,
                ];
            }
        }

        return [
            'workspaces' => $workspaces,
            'full_view_available' => $this->fullViewAvailable($user),
        ];
    }

    /**
     * Menu of one workspace.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ModelNotFoundException unknown slug or nothing visible to the user
     */
    public function menu(User $user, string $slug): array
    {
        $workspace = Workspace::query()->with('items')->where('slug', $slug)->firstOrFail();
        $tree = $this->navigation->prune($this->navigation->for($user), $workspace->navigationKeys());

        if ($tree === []) {
            throw new ModelNotFoundException;
        }

        return $tree;
    }

    /**
     * Menu without a workspace: the full tree when allowed, nothing otherwise.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fullMenu(User $user): array
    {
        return $this->fullViewAvailable($user) ? $this->navigation->for($user) : [];
    }

    private function fullViewAvailable(User $user): bool
    {
        return $user->hasRole(UserService::PRIVILEGED_ROLE) || ! Workspace::query()->exists();
    }

    /**
     * @return Collection<int, Workspace>
     */
    private function all(): Collection
    {
        return Workspace::query()->with('items')->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Route of the first assigned item of the pruned tree, in menu order.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @param  array<int, string>  $keys
     */
    private function homeRoute(array $tree, array $keys): ?string
    {
        foreach ($tree as $item) {
            if (! empty($item['route']) && in_array($item['key'], $keys, true)) {
                return $item['route'];
            }

            $route = $this->homeRoute($item['children'] ?? [], $keys);
            if ($route !== null) {
                return $route;
            }
        }

        return null;
    }
}
