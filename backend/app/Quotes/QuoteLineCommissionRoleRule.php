<?php

declare(strict_types=1);

namespace App\Quotes;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * `{field}.*.commissions.*.recipient_role` is unique WITHIN one line (DB
 * unique on quote_line_id + recipient_role). Laravel's `distinct` cannot say
 * that: on a nested wildcard it compares every line's commissions together,
 * so a quote whose lines all pay the same reporter was rejected.
 */
final class QuoteLineCommissionRoleRule implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $field) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = '/^'.preg_quote($this->field, '/').'\.(\d+)\.commissions\.(\d+)\.recipient_role$/';

        if (preg_match($pattern, $attribute, $matches) !== 1) {
            return;
        }

        $commissions = data_get($this->data, "{$this->field}.{$matches[1]}.commissions");

        foreach (is_array($commissions) ? $commissions : [] as $index => $commission) {
            if ((string) $index !== $matches[2] && data_get($commission, 'recipient_role') === $value) {
                $fail('validation.distinct')->translate();

                return;
            }
        }
    }
}
