<?php

namespace App\Migrations\Sources;

use App\DataObjects\CommissionConfigurations\CreateCommissionConfigurationData;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Services\CommissionConfigurationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * `commission-configurations` migration source (spec 0203, D-5/D-6): one
 * CommissionConfiguration per legacy `service_commissions` rule, created
 * through CommissionConfigurationService and tagged with `old_id`.
 *
 * The recipient is remapped by the legacy KIND, never guessed from the bare id
 * (the legacy id spaces overlap, R-2): company -> Registry, contact ->
 * Referent, user -> User, each via `old_id`. A rule the Configuratore could not
 * hold is a FAILED row with a readable message, never an invented rule: no
 * role, no/unmigrated recipient, a recipient kind the role does not admit, no
 * unmigrated or non-selectable category/product, no amount. Written without
 * activity log: a migration notifies and audits nobody (as spec 0189).
 */
class CommissionConfigurationsSource extends AbstractMigrationSource
{
    /** Legacy role key -> qnet role. */
    private const array ROLES = [
        'commercial' => CommissionRecipientRole::Commercial,
        'reporter' => CommissionRecipientRole::Reporter,
        'supervisor' => CommissionRecipientRole::Supervisor,
        'supplier' => CommissionRecipientRole::Supplier,
    ];

    /**
     * Legacy recipient kind -> the model its `recipient_id` is remapped onto.
     *
     * @var array<string, class-string<Model>>
     */
    private const array RECIPIENT_MODELS = [
        'company' => Registry::class,
        'contact' => Referent::class,
        'user' => User::class,
    ];

    private const int DEFAULT_PRIORITY = 0;

    private const int NAME_MAX_LENGTH = 191;

    private const int VALUE_SCALE = 4;

    public function __construct(ExternalApiClient $client, private readonly CommissionConfigurationService $service)
    {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'commission-configurations';
    }

    public function label(): string
    {
        return 'Commission configurations';
    }

    public function endpoint(): string
    {
        return 'commission-configurations';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'role', 'label' => 'Role', 'type' => 'string'],
            ['id' => 'recipient_kind', 'label' => 'Recipient kind', 'type' => 'string'],
            ['id' => 'recipient_id', 'label' => 'Recipient (external id)', 'type' => 'number'],
            ['id' => 'category_id', 'label' => 'Category (external id)', 'type' => 'number'],
            ['id' => 'product_id', 'label' => 'Product (external id)', 'type' => 'number'],
            ['id' => 'percentage', 'label' => 'Percentage', 'type' => 'number'],
            ['id' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['id' => 'note', 'label' => 'Note', 'type' => 'string'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'string'],
        ];
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
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(CommissionConfiguration::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        // Step 1: the role and its remapped recipient
        $role = $this->resolveRole($record['role'] ?? null);
        $recipient = $this->resolveRecipient($role, $record['recipient_kind'] ?? null, $record['recipient_id'] ?? null);

        // Step 2: the destination (product wins over category) and the amount
        $destination = $this->resolveDestination($record['product_id'] ?? null, $record['category_id'] ?? null);
        [$type, $value] = $this->resolveAmount($record['percentage'] ?? null, $record['amount'] ?? null);

        // Step 3: create through the domain service, then anchor to the legacy id
        $configuration = $this->service->create(new CreateCommissionConfigurationData(
            name: $this->buildName($role, $recipient, $destination['model']),
            recipientRole: $role,
            applicationScope: $destination['scope'],
            productCategoryId: $destination['scope'] === CommissionApplicationScope::ProductCategory ? $destination['model']->id : null,
            productId: $destination['scope'] === CommissionApplicationScope::Product ? $destination['model']->id : null,
            recipientId: $recipient->id,
            recipientType: $recipient->getMorphClass(),
            commissionType: $type,
            value: $value,
            priority: self::DEFAULT_PRIORITY,
            validFrom: $this->validFrom($record['created_at'] ?? null),
            validUntil: null,
            status: CommissionConfigurationStatus::Active,
            internalNote: $this->blankToNull($record['note'] ?? null),
        ));

        $configuration->old_id = $externalId;
        $configuration->save();

        return MigrationRowOutcome::created([], $configuration);
    }

    private function resolveRole(mixed $legacyRole): CommissionRecipientRole
    {
        $role = is_string($legacyRole) ? (self::ROLES[$legacyRole] ?? null) : null;

        return $role ?? throw new RuntimeException('The legacy rule has no commission role; it cannot be migrated.');
    }

    private function resolveRecipient(CommissionRecipientRole $role, mixed $kind, mixed $legacyId): Model
    {
        $modelClass = is_string($kind) ? (self::RECIPIENT_MODELS[$kind] ?? null) : null;

        if ($modelClass === null || $legacyId === null || $legacyId === '') {
            throw new RuntimeException('The legacy rule has no recipient; it cannot be migrated.');
        }

        $alias = (new $modelClass)->getMorphClass();

        if (! in_array($alias, $role->allowedRecipientTypes(), true)) {
            throw new RuntimeException("Recipient kind '{$kind}' is not allowed for the {$role->value} role.");
        }

        $recipient = $modelClass::query()->where('old_id', $legacyId)->first();

        return $recipient ?? throw new RuntimeException("Recipient not migrated (legacy {$kind} id {$legacyId}); migrate the registries, referents and users first.");
    }

    /**
     * @return array{scope: CommissionApplicationScope, model: Product|ProductCategory}
     */
    private function resolveDestination(mixed $legacyProductId, mixed $legacyCategoryId): array
    {
        if ($legacyProductId !== null && $legacyProductId !== '') {
            $product = Product::query()->where('old_source', ProductsSource::OLD_SOURCE)->where('old_id', $legacyProductId)->first();

            return [
                'scope' => CommissionApplicationScope::Product,
                'model' => $product ?? throw new RuntimeException("Product not migrated (legacy id {$legacyProductId}); migrate products first."),
            ];
        }

        if ($legacyCategoryId === null || $legacyCategoryId === '') {
            throw new RuntimeException('The legacy rule has neither a category nor a product; it cannot be migrated.');
        }

        $category = ProductCategory::query()->where('old_id', $legacyCategoryId)->first();

        if ($category === null) {
            throw new RuntimeException("Category not migrated (legacy id {$legacyCategoryId}); migrate product-categories first.");
        }

        // Same rule the Configuratore enforces (spec 0074): a container category can never fire.
        if (! $category->is_selectable) {
            throw new RuntimeException("Category '{$category->name}' is not selectable; a commission rule cannot target it.");
        }

        return ['scope' => CommissionApplicationScope::ProductCategory, 'model' => $category];
    }

    /**
     * @return array{0: CommissionType, 1: string}
     */
    private function resolveAmount(mixed $percentage, mixed $amount): array
    {
        if (is_numeric($percentage) && (float) $percentage > 0) {
            return [CommissionType::Percentage, number_format((float) $percentage, self::VALUE_SCALE, '.', '')];
        }

        if (is_numeric($amount) && (float) $amount > 0) {
            return [CommissionType::FixedAmount, number_format((float) $amount, self::VALUE_SCALE, '.', '')];
        }

        throw new RuntimeException('The legacy rule has neither a percentage nor an amount; it cannot be migrated.');
    }

    private function buildName(CommissionRecipientRole $role, Model $recipient, Model $destination): string
    {
        return mb_substr("Legacy - {$role->label()} - {$recipient->name} - {$destination->name}", 0, self::NAME_MAX_LENGTH);
    }

    private function validFrom(mixed $createdAt): string
    {
        $text = $this->blankToNull($createdAt);

        return ($text === null ? Carbon::today() : Carbon::parse($text))->toDateString();
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
