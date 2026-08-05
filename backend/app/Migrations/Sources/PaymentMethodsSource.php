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
 * payment modality lookup created through PaymentMethodService, so
 * `sort_order` stays server-managed by PaymentMethodOrderManager exactly as on
 * the CRUD path. An independent phase-1 anchor: nothing it imports references
 * another source, while the modules that consume it (quotes first) point at it
 * via `old_id`.
 *
 * The external `code` is the FISCAL classification ("MP01", "MP05", ...),
 * shared by many modalities: it is imported as-is into `payment_method_code`,
 * and qnet's own unique `code` is derived from the name instead (mapCode()).
 * Re-import is idempotent (skip by old_id); a method already provisioned under
 * the same derived code and not yet bound to an external record — the seeded
 * catalogue — is ADOPTED rather than duplicated (mirrors SourcesSource).
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

    /** Same ceiling as the `code` column / StorePaymentMethodRequest. */
    private const int CODE_MAX_LENGTH = 64;

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
            // The external field name is kept (the preview mirrors the source
            // record); it lands on qnet's `payment_method_code`.
            ['id' => 'code', 'label' => 'Fiscal code', 'type' => 'string'],
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

        // Step 2: the name — a plain, non-unique label — and the qnet `code`
        // derived from it, which IS the unique identity.
        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $code = $this->mapCode($name, $externalId);

        // Step 3: adopt the row the clean/demo catalogue already provisioned
        // under this code, instead of colliding with its unique index.
        $adopted = $this->adopt($code, $externalId);

        if ($adopted !== null) {
            return MigrationRowOutcome::created(model: $adopted);
        }

        // Step 4: create through the domain Service, then stamp old_id.
        $paymentDays = $this->mapPaymentDays($record['payment_days'] ?? null);

        $paymentMethod = $this->service->create(new CreatePaymentMethodData(
            name: $name,
            code: $code,
            paymentMethodCode: $this->mapText($record['code'] ?? null),
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
     * Claim the payment method already present under this code and not yet
     * bound to an external record (the seeded catalogue): a second row with
     * the same code cannot exist, and re-importing must not duplicate it.
     */
    private function adopt(string $code, int|string $externalId): ?PaymentMethod
    {
        $existing = PaymentMethod::query()->where('code', $code)->whereNull('old_id')->first();

        if ($existing === null) {
            return null;
        }

        $existing->old_id = $externalId;
        $existing->save();

        return $existing;
    }

    /**
     * qnet's unique `code` is derived from the NAME, never from the external
     * `code`: the latter is the fiscal classification (imported as-is into
     * `payment_method_code`) and is deliberately shared — "MP01" alone covers
     * a dozen legacy modalities, so using it as the identity collapsed the
     * whole catalogue onto a handful of rows.
     *
     * Derivation is deterministic (same name -> same code across runs), which
     * is what keeps adoption and idempotence stable. Two DIFFERENT names that
     * slugify identically (homonyms are legal now that `name` is not unique)
     * fall back to a code suffixed with the external id — still deterministic,
     * unique by construction.
     */
    private function mapCode(string $name, int|string $externalId): string
    {
        $base = $this->slugify($name);
        $owner = PaymentMethod::query()->where('code', $base)->first();

        // Free, or held by a row this record is about to adopt.
        if ($owner === null || $owner->old_id === null) {
            return $base;
        }

        $suffixed = $this->slugify($name, (string) $externalId);

        if (PaymentMethod::query()->where('code', $suffixed)->exists()) {
            throw new RuntimeException("code \"{$suffixed}\" is already taken.");
        }

        return $suffixed;
    }

    /**
     * The name as a snake_case identifier (`^[a-z][a-z0-9_]*$`, D-3), capped
     * at the column length with room for the optional disambiguating suffix.
     */
    private function slugify(string $name, string $suffix = ''): string
    {
        $slug = trim((string) preg_replace('/_+/', '_', (string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($name))), '_');

        if ($slug === '') {
            throw new RuntimeException('code could not be derived from the name.');
        }

        if (! ctype_alpha($slug[0])) {
            $slug = self::DERIVED_CODE_PREFIX.$slug;
        }

        $tail = $suffix === '' ? '' : '_'.$suffix;

        return mb_substr($slug, 0, self::CODE_MAX_LENGTH - mb_strlen($tail)).$tail;
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
