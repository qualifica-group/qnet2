<?php

namespace App\Migrations\Sources;

use App\DataObjects\PaymentMethods\CreatePaymentMethodData;
use App\DataObjects\PaymentMethods\UpdatePaymentMethodData;
use App\Enums\PaymentCondition;
use App\Enums\VatAllocation;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use RuntimeException;

/**
 * `payment-methods` migration source (spec 0013 / 0068 / 0325): the
 * consumer-agnostic payment modality lookup created through
 * PaymentMethodService, so `sort_order` stays server-managed by
 * PaymentMethodOrderManager exactly as on the CRUD path. An independent
 * phase-1 anchor: nothing it imports references another source, while the
 * modules that consume it (quotes first) point at it via `old_id`.
 *
 * The legacy sends every field of its `payments` table already in qnet's
 * vocabulary (spec 0325, D-4). The external `payment_method_code` is the
 * FISCAL classification ("MP01", "MP05", ...), shared by many modalities.
 * qnet's own unique `code` arrives ready (snake_case, unique over the whole
 * table) and is used as sent.
 *
 * Re-import OVERWRITES (spec 0325, D-3): a method already imported (same
 * old_id) gets the legacy fields again and counts as skipped; `code`,
 * `sort_order`, `is_active`, `description` and `payment_instructions` stay
 * qnet's. A method already provisioned under the same code and not yet bound
 * to an external record (the seeded catalogue) is ADOPTED and overwritten the
 * same way rather than duplicated (mirrors SourcesSource).
 */
class PaymentMethodsSource extends AbstractMigrationSource
{
    /** Same format and ceiling as the `code` column / StorePaymentMethodRequest. */
    private const string CODE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    private const int CODE_MAX_LENGTH = 64;

    /**
     * Bounds of the numeric legacy fields, the same StorePaymentMethodRequest
     * and ValidatesInstallmentConfig enforce: a value outside them is a
     * non-fatal warning and is left out, never silently clamped.
     *
     * @var array<string, array{int, int}>
     */
    private const array INTEGER_BOUNDS = [
        'payment_days' => [0, 3650],
        'installments_count' => [1, 60],
        'days_between_installments' => [0, 365],
        'end_of_month_extra_days' => [0, 31],
        'excluded_month_1' => [1, 12],
        'excluded_month_2' => [1, 12],
    ];

    /** @var array<string, class-string<\BackedEnum>> */
    private const array ENUM_FIELDS = [
        'payment_condition' => PaymentCondition::class,
        'vat_allocation' => VatAllocation::class,
    ];

    public function __construct(
        ExternalApiClient $client,
        private readonly PaymentMethodService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'payment-methods';
    }

    public function label(): string
    {
        return 'Payment methods';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'code', 'label' => 'Code', 'type' => 'string'],
            ['id' => 'payment_method_code', 'label' => 'Fiscal code', 'type' => 'string'],
            ['id' => 'payment_condition', 'label' => 'Payment condition', 'type' => 'string'],
            ['id' => 'vat_allocation', 'label' => 'VAT allocation', 'type' => 'string'],
            ['id' => 'payment_days', 'label' => 'Payment days', 'type' => 'number'],
            ['id' => 'installments_count', 'label' => 'Installments', 'type' => 'number'],
            ['id' => 'days_between_installments', 'label' => 'Days between installments', 'type' => 'number'],
            ['id' => 'end_of_month', 'label' => 'End of month', 'type' => 'boolean'],
            ['id' => 'end_of_month_extra_days', 'label' => 'Extra days', 'type' => 'number'],
            ['id' => 'excluded_month_1', 'label' => 'Excluded month 1', 'type' => 'number'],
            ['id' => 'excluded_month_2', 'label' => 'Excluded month 2', 'type' => 'number'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'payment_instructions', 'label' => 'Payment instructions', 'type' => 'string'],
            ['id' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
        ];
    }

    public function endpoint(): string
    {
        return 'payment-methods';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        $row = [];

        foreach ($this->nativeColumns() as $column) {
            $row[$column['id']] = $record[$column['id']] ?? null;
        }

        return $row;
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        // Step 1: the external id anchors both the create and the overwrite.
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        // Step 2: the legacy-owned fields, checked defensively.
        $warnings = [];
        $legacy = $this->legacyAttributes($record, $warnings);

        // Step 3: an already imported method is overwritten (D-3).
        $existing = PaymentMethod::query()->where('old_id', $externalId)->first();

        if ($existing !== null) {
            $this->overwrite($existing, $legacy);

            return MigrationRowOutcome::skipped($warnings);
        }

        // Step 4: adopt the row the clean/demo catalogue already provisioned
        // under this code, instead of colliding with its unique index.
        $code = $this->validCode($record['code'] ?? null);
        $adopted = PaymentMethod::query()->where('code', $code)->whereNull('old_id')->first();

        if ($adopted !== null) {
            $adopted->old_id = $externalId;
            $this->overwrite($adopted, $legacy);

            return MigrationRowOutcome::created($warnings, $adopted);
        }

        // Step 5: create through the domain Service, then stamp old_id.
        $paymentMethod = $this->service->create(CreatePaymentMethodData::fromValidated([
            ...$legacy,
            'code' => $code,
            'description' => $this->mapText($record['description'] ?? null),
            'payment_instructions' => $this->mapText($record['payment_instructions'] ?? null),
            'is_active' => $this->mapIsActive($record['is_active'] ?? null),
        ]));

        $paymentMethod->old_id = $externalId;
        $paymentMethod->save();

        return MigrationRowOutcome::created($warnings, $paymentMethod);
    }

    /**
     * The fields the legacy owns, in the shape of a validated request payload.
     * A blank number is null (it clears a nullable field); an invalid enum or
     * number is left out with a warning, so a create falls back to the default
     * and an overwrite keeps the current value.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function legacyAttributes(array $record, array &$warnings): array
    {
        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $attributes = [
            'name' => $name,
            'payment_method_code' => $this->mapText($record['payment_method_code'] ?? null),
            'end_of_month' => filter_var($record['end_of_month'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        foreach (self::INTEGER_BOUNDS as $field => [$min, $max]) {
            $value = $record[$field] ?? null;

            if ($this->isBlank($value)) {
                $attributes[$field] = null;
            } elseif (is_numeric($value) && (int) $value >= $min && (int) $value <= $max) {
                $attributes[$field] = (int) $value;
            } else {
                $warnings[] = $field.' "'.((string) $value).'" is not a number between '.$min.' and '.$max.' and was not imported.';
            }
        }

        foreach (self::ENUM_FIELDS as $field => $enum) {
            $value = $record[$field] ?? null;

            if ($this->isBlank($value)) {
                continue;
            }

            if ($enum::tryFrom((string) $value) !== null) {
                $attributes[$field] = (string) $value;
            } else {
                $warnings[] = $field.' "'.((string) $value).'" is not a known value and was not imported.';
            }
        }

        return $attributes;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    /**
     * @param  array<string, mixed>  $legacy
     */
    private function overwrite(PaymentMethod $paymentMethod, array $legacy): void
    {
        activity()->withoutLogs(fn () => $this->service->update($paymentMethod, UpdatePaymentMethodData::fromValidated($legacy)));
    }

    /**
     * Defensive check of the `code` the legacy sends: the format and ceiling
     * StorePaymentMethodRequest enforces, which the import bypasses.
     */
    private function validCode(mixed $code): string
    {
        $code = trim((string) ($code ?? ''));

        if (preg_match(self::CODE_PATTERN, $code) !== 1 || mb_strlen($code) > self::CODE_MAX_LENGTH) {
            throw new RuntimeException('code is required and must match '.self::CODE_PATTERN.' (max '.self::CODE_MAX_LENGTH.' characters).');
        }

        return $code;
    }

    private function mapText(mixed $externalValue): ?string
    {
        $text = trim((string) ($externalValue ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * An absent flag defaults to active (same default as
     * CreatePaymentMethodData): the external catalogue lists modalities in use.
     */
    private function mapIsActive(mixed $externalValue): bool
    {
        if ($externalValue === null || $externalValue === '') {
            return true;
        }

        return filter_var($externalValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
