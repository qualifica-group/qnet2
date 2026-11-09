<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Exceptions\ExternalApiException;
use App\Migrations\Support\ExternalApiClient;

/**
 * The two legacy calls of the realignment (spec 0375 data_contract), on the same
 * authenticated client as the import.
 */
final class LegacyRealignClient
{
    public const int MAX_IDS_PER_REQUEST = 100;

    private const string CHANGED_RECORDS_PATH = 'changed-records/';

    public function __construct(private readonly ExternalApiClient $client) {}

    /**
     * @return array{changed: list<string>, deleted: list<string>}
     *
     * @throws ExternalApiException
     */
    public function changes(string $source, string $since): array
    {
        $payload = $this->client->get(self::CHANGED_RECORDS_PATH.$source, ['since' => $since]);

        return [
            'changed' => array_map(strval(...), array_values((array) ($payload['changed'] ?? []))),
            'deleted' => array_map(strval(...), array_values((array) ($payload['deleted'] ?? []))),
        ];
    }

    /**
     * @param  list<string>  $legacyKeys  at most MAX_IDS_PER_REQUEST
     * @return list<array<string, mixed>>
     *
     * @throws ExternalApiException
     */
    public function records(string $endpoint, array $legacyKeys): array
    {
        $payload = $this->client->get($endpoint, [
            'only_ids' => implode(',', $legacyKeys),
            'limit' => self::MAX_IDS_PER_REQUEST,
        ]);

        return array_values(array_filter((array) ($payload['items'] ?? []), is_array(...)));
    }
}
