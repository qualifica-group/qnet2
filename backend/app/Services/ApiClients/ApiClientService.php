<?php

namespace App\Services\ApiClients;

use App\Models\ApiClient;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Lifecycle of the API integration clients (specs 0209, 0210): the client row,
 * its technical super-admin user and the key, which is a Sanctum token of that
 * user bound to the client (`api_client_id`).
 *
 * The plain-text key exists only in the return value of create()/rotateKey():
 * it is never stored, logged, or written to the activity log. Only its last
 * four characters are kept (`key_last_four`), written without activity logging
 * so the audit trail never carries any fragment of the key.
 */
class ApiClientService
{
    private const string SERVICE_USER_NAME_PREFIX = 'API · ';

    private const string KEY_NAME_PREFIX = 'api-client:';

    private const int KEY_HINT_LENGTH = 4;

    // The roles table guard: the request default guard is `sanctum` on the API.
    private const string ROLE_GUARD = 'web';

    private const int SERVICE_USER_PASSWORD_LENGTH = 64;

    /**
     * @param  array<string, mixed>  $data  validated StoreApiClientRequest payload
     * @return array{client: ApiClient, plain_text_key: string}
     */
    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            // Step 1: the technical user that will author the writes made with the key
            $serviceUser = $this->createServiceUser($data['name']);

            // Step 2: persist the client, owned by the acting admin
            $client = new ApiClient([...$data, 'created_by' => $actor->id]);
            $client->service_user_id = $serviceUser->id;
            $client->save();

            // Step 3: issue its single key
            $plainTextKey = $this->issueKey($client, $serviceUser);

            return ['client' => $this->loadDetail($client), 'plain_text_key' => $plainTextKey];
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated UpdateApiClientRequest payload
     */
    public function update(ApiClient $client, array $data): ApiClient
    {
        return DB::transaction(function () use ($client, $data): ApiClient {
            // Step 1: apply the new attributes
            $client->update($data);

            // Step 2: the technical user follows the client name
            if ($client->wasChanged('name')) {
                $client->serviceUser->update(['name' => self::serviceUserName($client->name)]);
            }

            return $this->loadDetail($client);
        });
    }

    /**
     * Revoke the previous key and issue a new one. The user tokens issued
     * through client-login are left alone.
     *
     * @return array{client: ApiClient, plain_text_key: string}
     */
    public function rotateKey(ApiClient $client, User $actor): array
    {
        return DB::transaction(function () use ($client, $actor): array {
            // Step 1: revoke the current key (tokens of the technical user only)
            $client->tokens()
                ->where('tokenable_type', $client->serviceUser->getMorphClass())
                ->where('tokenable_id', $client->service_user_id)
                ->delete();

            // Step 2: issue the replacement
            $plainTextKey = $this->issueKey($client, $client->serviceUser);

            // Step 3: explicit audit entry (the only column that moved is the key hint)
            activity()
                ->performedOn($client)
                ->causedBy($actor)
                ->event('key_rotated')
                ->useLog($client->getTable())
                ->log('key_rotated');

            return ['client' => $this->loadDetail($client), 'plain_text_key' => $plainTextKey];
        });
    }

    /**
     * Deleting the client cascades to every token bound to it (key and
     * client-login tokens); the technical user is kept, deactivated, so the
     * activity log causer never dangles.
     */
    public function delete(ApiClient $client): void
    {
        DB::transaction(function () use ($client): void {
            $client->serviceUser->update(['is_active' => false]);
            $client->delete();
        });
    }

    /**
     * Eager-load what ApiClientResource reads, so it never lazy-loads.
     */
    public function loadDetail(ApiClient $client): ApiClient
    {
        return $client->load('creator', 'serviceUser')->loadMax('tokens', 'last_used_at');
    }

    private function createServiceUser(string $clientName): User
    {
        $user = new User([
            'name' => self::serviceUserName($clientName),
            'email' => 'svc-'.Str::lower((string) Str::ulid()).'@'.config('external-api.service_users.email_domain'),
            'password' => Str::random(self::SERVICE_USER_PASSWORD_LENGTH),
            'is_active' => true,
        ]);
        $user->forceFill(['is_service_account' => true])->save();

        $user->assignRole(Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE, self::ROLE_GUARD));

        return $user;
    }

    private function issueKey(ApiClient $client, User $serviceUser): string
    {
        $newToken = $serviceUser->createToken(self::KEY_NAME_PREFIX.$client->id);
        $newToken->accessToken->forceFill(['api_client_id' => $client->id])->save();

        $plainTextKey = $newToken->plainTextToken;

        activity()->withoutLogs(
            fn () => $client->update(['key_last_four' => substr($plainTextKey, -self::KEY_HINT_LENGTH)]),
        );

        return $plainTextKey;
    }

    private static function serviceUserName(string $clientName): string
    {
        return self::SERVICE_USER_NAME_PREFIX.$clientName;
    }
}
