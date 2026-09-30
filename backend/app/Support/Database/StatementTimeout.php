<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;

/**
 * Per-session statement time limit and detection of its timeout error.
 * MariaDB enforces it on every statement (seconds); MySQL only on SELECT (ms).
 */
final class StatementTimeout
{
    public const MARIADB_ERROR_CODE = 1969;

    public const MYSQL_ERROR_CODE = 3024;

    /** SQL to run once per session, or null when the connection is not MySQL/MariaDB. */
    public static function statementFor(Connection $connection, int $seconds): ?string
    {
        if ($seconds <= 0 || ! $connection instanceof MySqlConnection) {
            return null;
        }

        return $connection->isMaria()
            ? sprintf('SET SESSION max_statement_time = %d', $seconds)
            : sprintf('SET SESSION max_execution_time = %d', $seconds * 1000);
    }

    public static function isTimeout(QueryException $exception): bool
    {
        $driver_code = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($driver_code, [self::MARIADB_ERROR_CODE, self::MYSQL_ERROR_CODE], true);
    }
}
