<?php

namespace App\Migrations\Sources;

use App\DataObjects\PaymentMethods\CreatePaymentMethodData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use RuntimeException;

/**
 * `payment-methods` migration source (spec 0013 / 0068): the consumer-agnostic
 * payment modality lookup (name, code, description, payment_instructions,
 * payment_days, is_active) created through PaymentMethodService, so
 * `sort_order` stays server-managed by PaymentMethodOrderManager exactly as on
 * the CRUD path. An independent phase-1 anchor: nothing it imports references
 * another source, while the modules that consume it (quotes first) point at it
 * via `old_id`.
 *
 * Re-import is idempotent (skip by old_id); a method already provisioned under
 * the same `code` and not yet claimed by another external id — the seeded
 * catalogue — is ADOPTED rather than duplicated (mirrors SourcesSource), since
 * both `code` and `name` carry a unique index and a second row would be an
 * unusable duplicate in every select.
 */
class PaymentMethodsSource extends AbstractMigrationSource
{
    /**
     * Same ceiling as StorePaymentMethodRequest: a larger external value is a
     * non-fatal warning (the modality is still worth importing), never a
     * silently truncated payment term.
     */
    private const int PAYMENT_DAYS_MAX = 3650;

    /**
     * Prefix for a code derived from a name that does not start with a letter
     * (`code` must match `^[a-z][a-z0-9_]*$`, D-3 of spec 0068).
     */
    private const string DERIVED_CODE_PREFIX = 'pm_';

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
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'payment_instructions', 'label' => 'Payment instructions', 'type' => 'string'],
            ['id' => 'payment_days', 'label' => 'Payment days', 'type' => 'number'],
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
        return [
            'id' => $record['id'] ?? null,
            'name' => $record['name'] ?? null,
            'code' => $record['code'] ?? null,
            'description' => $record['description'] ?? null,
            'payment_instructions' => $record['payment_instructions'] ?? null,
            'payment_days' => $record['payment_days'] ?? null,
            'is_active' => $record['is_active'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        // Step 1: idempotence — an already imported external id is skipped.
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(PaymentMethod::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        // Step 2: the two unique identities (name/code), both required here.
        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $code = $this->mapCode($record['code'] ?? null, $name);

        // Step 3: adopt the row the clean/demo catalogue already provisioned
        // under this code, instead of colliding with its unique index.
        $adopted = $this->adopt($code, $externalId);

        if ($adopted !== null) {
            return MigrationRowOutcome::created(model: $adopted);
        }

        $this->assertNameAvailable($name);

        // Step 4: create through the domain Service, then stamp old_id.
        $paymentDays = $this->mapPaymentDays($record['payment_days'] ?? null);

        $paymentMethod = $this->service->create(new CreatePaymentMethodData(
            name: $name,
            code: $code,
            description: $this->mapText($record['description'] ?? null),
            paymentInstructions: $this->mapText($record['payment_instructions'] ?? null),
            paymentDays: $paymentDays,
            isActive: $this->mapIsActive($record['is_active'] ?? null),
        ));

        $paymentMethod->old_id = $externalId;
        $paymentMethod->save();

        return MigrationRowOutcome::created(
            warnings: $this->paymentDaysWarnings($record['payment_days'] ?? null, $paymentDays),
            model: $paymentMethod,
        );
    }

    /**
     * Claim the payment method already present under this code when it carries
     * no external id yet. A code already claimed by a DIFFERENT external record
     * is a fatal per-row error: `code` is the immutable identity (D-3), so two
     * external rows mapping onto it means the external catalogue is ambiguous.
     */
    private function adopt(string $code, int|string $externalId): ?PaymentMethod
    {
        $existing = PaymentMethod::query()->where('code', $code)->first();

        if ($existing === null) {
            return null;
        }

        if ($existing->old_id !== null) {
            throw new RuntimeException("code \"{$code}\" is already migrated under a different external id.");
        }

        $existing->old_id = $externalId;
        $existing->save();

        return $existing;
    }

    /**
     * `name` is unique too, so a name already taken by another (differently
     * coded) method cannot be imported — surfaced as a readable per-row error
     * instead of a raw unique-constraint failure.
     */
    private function assertNameAvailable(string $name): void
    {
        if (PaymentMethod::query()->where('name', $name)->exists()) {
            throw new RuntimeException("name \"{$name}\" is already used by another payment method.");
        }
    }

    /**
     * The external `code` when it already matches the required shape,
     * otherwise one derived from it (or, when absent, from the name):
     * lowercased, every other character collapsed into a single underscore.
     * Deriving is deterministic and keeps the adoption/idempotence key stable
     * across runs — unlike inventing a value, it carries no new information.
     */
    private function mapCode(mixed $externalCode, string $name): string
    {
        $raw = trim((string) ($externalCode ?? ''));
        $source = $raw !== '' ? $raw : $name;

        $code = trim((string) preg_replace('/_+/', '_', (string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($source))), '_');

        if ($code === '') {
            throw new RuntimeException('code could not be derived from the record.');
        }

        return ctype_alpha($code[0]) ? $code : self::DERIVED_CODE_PREFIX.$code;
    }

    /**
     * A payment term is optional: absent/blank stays null. A value outside
     * [0, PAYMENT_DAYS_MAX] is discarded (null) rather than clamped — see
     * paymentDaysWarnings().
     */
    private function mapPaymentDays(mixed $externalDays): ?int
    {
        if (! is_numeric($externalDays)) {
            return null;
        }

        $days = (int) $externalDays;

        return $days >= 0 && $days <= self::PAYMENT_DAYS_MAX ? $days : null;
    }

    /**
     * @return array<int, string>
     */
    private function paymentDaysWarnings(mixed $externalDays, ?int $mapped): array
    {
        $wasProvided = $externalDays !== null && trim((string) $externalDays) !== '';

        if (! $wasProvided || $mapped !== null) {
            return [];
        }

        return ['payment_days "'.((string) $externalDays).'" is not a valid number of days and was left empty.'];
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
