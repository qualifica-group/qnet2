<?php

namespace App\Console\Commands;

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Policies\Abstracts\BasePolicy;
use App\Services\NavigationService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all permissions referenced by the navigation config and the resource policies';

    /**
     * Execute the console command.
     */
    public function handle(NavigationService $navigation, ProtectedFieldRegistry $protectedFields): int
    {
        $permissions = array_values(array_unique(array_merge(
            $navigation->permissions(),
            $this->policyPermissions(),
            $protectedFields->permissions(),
        )));

        if ($permissions === []) {
            $this->info('No permissions found in navigation config.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name]);

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("  <info>created</info> {$name}");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf(
            'Permissions synced: %d created, %d already existed.',
            $created,
            count($permissions) - $created
        ));

        return self::SUCCESS;
    }

    /**
     * Standard permissions declared by every resource policy (BasePolicy).
     *
     * @return array<int, string>
     */
    private function policyPermissions(): array
    {
        $permissions = [];

        foreach (glob(app_path('Policies/*.php')) as $file) {
            $class = 'App\\Policies\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class) || ! is_subclass_of($class, BasePolicy::class)) {
                continue;
            }

            $permissions = array_merge($permissions, (new $class)->permissions());
        }

        return $permissions;
    }
}
