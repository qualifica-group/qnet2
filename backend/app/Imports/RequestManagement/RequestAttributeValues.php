<?php

namespace App\Imports\RequestManagement;

use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeValueValidator;
use Illuminate\Validation\ValidationException;

/**
 * Turns the cells mapped to `attribute.<code>` targets into the Offerta's
 * "Informazioni aggiuntive" values (spec 0348, AC-009): each cell is read as
 * the attribute's type ("700,00 EUR" -> 700.0, an enum by label or value, a
 * date) and then checked with the SAME AttributeValueValidator the Offerta
 * write path runs, so a value kept here can never fail the row later. A cell
 * that cannot be read or fails the rules is reported, never thrown.
 */
final class RequestAttributeValues
{
    public const string TARGET_PREFIX = 'attribute.';

    /** Attribute types a text cell can express; relation and table ones cannot. */
    public const array IMPORTABLE_TYPES = ['text', 'textarea', 'color', 'integer', 'decimal', 'boolean', 'enum', 'date', 'datetime', 'time', 'email', 'url'];

    private const array TRUE_WORDS = ['si', 'yes', 'true', '1', 'x', 'vero'];

    private const array FALSE_WORDS = ['no', 'false', '0', 'falso'];

    private const string DATETIME_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        private readonly RequestImportLookups $lookups,
        private readonly ImportDateParser $dates,
        private readonly AttributeValueValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $mapped  field id => cell (the `attribute.*` keys are read)
     * @return array{values: array<string, mixed>, rejected: array<int, array{label: string, value: string}>}
     */
    public function resolve(int $categoryId, array $mapped): array
    {
        $attributes = $this->lookups->quoteAttributesOf($categoryId);
        $values = [];
        $rejected = [];

        foreach ($mapped as $target => $cell) {
            if (! str_starts_with((string) $target, self::TARGET_PREFIX)) {
                continue;
            }

            $raw = trim((string) $cell);
            $attribute = $attributes[substr((string) $target, strlen(self::TARGET_PREFIX))] ?? null;

            if ($raw === '' || $attribute === null) {
                continue;
            }

            $value = $this->accepted($attribute, $raw);

            if ($value === null) {
                $rejected[] = ['label' => (string) $attribute['name'], 'value' => $raw];
            } else {
                $values[(string) $attribute['code']] = $value;
            }
        }

        return ['values' => $values, 'rejected' => $rejected];
    }

    /**
     * @param  array<string, mixed>  $attribute
     */
    private function accepted(array $attribute, string $raw): mixed
    {
        $value = $this->read($attribute, $raw);

        if ($value === null || $value === []) {
            return null;
        }

        try {
            $this->validator->validate(
                collect([ApplicableAttribute::fromEffectiveAttributeRow($attribute)]),
                [(string) $attribute['code'] => $value],
            );
        } catch (ValidationException) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attribute
     */
    private function read(array $attribute, string $raw): mixed
    {
        return match ($attribute['type']) {
            'integer' => ($number = $this->number($raw)) === null ? null : (int) round($number),
            'decimal' => $this->number($raw),
            'boolean' => $this->boolean($raw),
            'enum' => $this->enum($attribute, $raw),
            'date' => $this->dates->parse($raw)?->format('Y-m-d'),
            'datetime' => $this->dates->parse($raw)?->format(self::DATETIME_FORMAT),
            'time' => $this->dates->parse('1970-01-01 '.$raw)?->format('H:i'),
            default => $raw,
        };
    }

    /**
     * "700,00 EUR", "1.234,50", "12.5" -> float; text with no digits -> null.
     */
    private function number(string $raw): ?float
    {
        $clean = (string) preg_replace('/[^0-9,.\-]/', '', $raw);

        if (! preg_match('/\d/', $clean)) {
            return null;
        }

        $clean = str_contains($clean, ',') ? str_replace(',', '.', str_replace('.', '', $clean)) : $clean;

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function boolean(string $raw): ?bool
    {
        return match (true) {
            in_array(RequestImportText::key($raw), self::TRUE_WORDS, true) => true,
            in_array(RequestImportText::key($raw), self::FALSE_WORDS, true) => false,
            default => null,
        };
    }

    /**
     * The option whose value or label equals the cell; a multi-select
     * attribute reads a comma/semicolon list and needs every item to match.
     *
     * @param  array<string, mixed>  $attribute
     * @return string|array<int, string>|null
     */
    private function enum(array $attribute, string $raw): string|array|null
    {
        $isMulti = ($attribute['config']['display'] ?? null) === 'multiselect';
        $items = $isMulti ? (preg_split('/[,;]/', $raw) ?: []) : [$raw];
        $picked = [];

        foreach ($items as $item) {
            $option = $this->option($attribute['options'] ?? [], $item);

            if ($option === null) {
                return null;
            }

            $picked[] = $option;
        }

        return $isMulti ? $picked : ($picked[0] ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function option(array $options, string $text): ?string
    {
        $key = RequestImportText::key($text);

        foreach ($options as $option) {
            if (RequestImportText::key((string) $option['value']) === $key || RequestImportText::key((string) $option['label']) === $key) {
                return (string) $option['value'];
            }
        }

        return null;
    }
}
