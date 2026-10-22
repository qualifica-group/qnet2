<?php

declare(strict_types=1);

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Versions of the server-side runtime shown on the System Health page
 * (spec 0187 rev. 2026-10-22): PHP, Laravel and the database server.
 * Informational only: it never affects the overall status.
 */
final class RuntimeVersionsService
{
    /**
     * @return array<int, array{key:string, value:?string}>
     */
    public function handle(): array
    {
        return [
            ['key' => 'php', 'value' => PHP_VERSION],
            ['key' => 'laravel', 'value' => app()->version()],
            ['key' => 'database', 'value' => $this->databaseVersion()],
        ];
    }

    /**
     * Driver title plus server version (e.g. "MySQL 8.0.36"); null when the
     * database is unreachable, which the database check already reports.
     */
    private function databaseVersion(): ?string
    {
        try {
            $connection = DB::connection();

            return $connection->getDriverTitle().' '.$connection->getServerVersion();
        } catch (Throwable) {
            return null;
        }
    }
}
