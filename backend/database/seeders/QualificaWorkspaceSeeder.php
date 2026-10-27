<?php

namespace Database\Seeders;

use App\Models\Workspace;
use App\Services\Workspaces\NavigationCatalogue;
use App\Services\Workspaces\WorkspaceService;
use Database\Seeders\QualificaCatalog\WorkspaceCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's three menu apps (spec 0317): Contabilità, HR and Sales, each
 * with the pages of its job. Create-only: an app whose slug already exists is
 * left as the super-admin configured it, so a re-run never undoes an edit
 * made in Develop > App.
 */
class QualificaWorkspaceSeeder extends Seeder
{
    public function __construct(
        private readonly WorkspaceService $workspaces,
        private readonly NavigationCatalogue $catalogue,
    ) {}

    public function run(): void
    {
        foreach (WorkspaceCatalogue::apps() as $app) {
            if (Workspace::query()->where('slug', $app['slug'])->exists()) {
                continue;
            }

            $this->workspaces->create([...$app, 'navigation_keys' => $this->knownKeys($app['slug'], $app['navigation_keys'])]);
        }
    }

    /**
     * A key no longer in the menu config is dropped with a warning instead of
     * failing the whole seed.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function knownKeys(string $slug, array $keys): array
    {
        $known = $this->catalogue->orderKeys($keys);

        foreach (array_diff($keys, $known) as $missing) {
            $this->command?->warn(sprintf('App %s: menu entry "%s" not found, skipped.', $slug, $missing));
        }

        return $known;
    }
}
