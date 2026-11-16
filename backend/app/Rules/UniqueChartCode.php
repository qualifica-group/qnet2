<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The code must be unused across the whole chart of accounts (spec 0348, D-5):
 * groups/accounts and the cost, revenue and financial sub-accounts (spec 0360)
 * share one namespace. The entry being updated is excluded so it can keep its own code.
 */
final class UniqueChartCode implements ValidationRule
{
    public const int MAX_LENGTH = 20;

    public const string FORMAT = '/^[A-Za-z0-9.\-]+$/';

    /** @var array<int, string> */
    private const array TABLES = ['chart_accounts', 'cost_types', 'revenue_types', 'treasury_types'];

    public function __construct(private readonly ?Model $ignore = null) {}

    /**
     * The full rule set of a code field (format, length, uniqueness).
     *
     * @return array<int, mixed>
     */
    public static function rules(?Model $ignore = null): array
    {
        return ['string', 'max:'.self::MAX_LENGTH, 'regex:'.self::FORMAT, new self($ignore)];
    }

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        foreach (self::TABLES as $table) {
            $query = DB::table($table)->where('code', $value);

            if ($this->ignore !== null && $this->ignore->getTable() === $table) {
                $query->where('id', '!=', $this->ignore->getKey());
            }

            if ($query->exists()) {
                $fail(__('chart_accounts.code_taken'));

                return;
            }
        }
    }
}
