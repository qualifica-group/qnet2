<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Costs;

/**
 * Decimal-string dollar arithmetic with 12 decimals (bcmath): no float ever touches a cap check.
 */
final class AiMoney
{
    public const int SCALE = 12;

    /** A number as it comes from JSON or the database (int, float or string) as a plain decimal string. */
    public static function from(int|float|string $value): string
    {
        return is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value) === 1
            ? bcadd($value, '0', self::SCALE)
            : number_format((float) $value, self::SCALE, '.', '');
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function mul(string $a, int|string $b): string
    {
        return bcmul($a, (string) $b, self::SCALE);
    }

    /** @return int -1, 0 or 1 */
    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function max(string $a, string $b): string
    {
        return self::compare($a, $b) >= 0 ? $a : $b;
    }

    /**
     * Split an amount into $parts equal shares that sum exactly to it (the first share takes the remainder).
     *
     * @return list<string>
     */
    public static function split(string $amount, int $parts): array
    {
        $base = bcdiv($amount, (string) $parts, self::SCALE);
        $shares = array_fill(0, $parts, $base);
        $shares[0] = self::add($base, self::sub($amount, self::mul($base, $parts)));

        return $shares;
    }
}
