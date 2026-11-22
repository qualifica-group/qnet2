<?php

namespace App\Services\McpLinks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Key that is equal for every spelling of an email address that the database
 * treats as the same (case, accents, as the collation of `users.email` says), and
 * that does not depend on whether the account exists.
 *
 * MySQL: the collation weight string of the address, with the collation read from
 * the column. Anywhere else (sqlite in tests) and whenever MySQL cannot produce
 * it (MariaDB without WEIGHT_STRING, a collation without weights): the lower-cased
 * address, which keeps case-insensitivity and leaves accent equivalence uncovered.
 */
class EmailCollationKey
{
    private const string TABLE = 'users';

    private const string COLUMN = 'email';

    private static ?string $collation = null;

    public function for(string $email): string
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return Str::lower($email);
        }

        try {
            $weights = DB::scalar('SELECT HEX(WEIGHT_STRING(? COLLATE '.($this->collation()).'))', [$email]);
        } catch (Throwable $e) {
            report($e);
            $weights = null;
        }

        // An empty weight string would put every address on one counter.
        return filled($weights) ? (string) $weights : Str::lower($email);
    }

    private function collation(): string
    {
        self::$collation ??= (string) DB::scalar(
            'SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [self::TABLE, self::COLUMN],
        );

        // Interpolated into the query: only a plain collation name is accepted.
        if (preg_match('/^[a-z0-9_]+$/i', self::$collation) !== 1) {
            self::$collation = null;

            throw new \RuntimeException('Unreadable collation of the email column.');
        }

        return self::$collation;
    }
}
