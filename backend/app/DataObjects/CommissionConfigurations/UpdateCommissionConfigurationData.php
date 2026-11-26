<?php

declare(strict_types=1);

namespace App\DataObjects\CommissionConfigurations;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;

final readonly class UpdateCommissionConfigurationData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $productCategoryIds  null = leave the saved list untouched
     * @param  list<int>|null  $productIds  null = leave the saved list untouched
     */
    public function __construct(
        public array $attributes,
        public ?array $productCategoryIds = null,
        public ?array $productIds = null,
    ) {}

    /**
     * Spec 0090 D-4 (emends 0089 D-7): `recipient_type` is (re)computed here
     * ONLY when `recipient_id` is actually part of this partial update — an
     * update that leaves `recipient_id` untouched must leave `recipient_type`
     * untouched too. When it IS part of the update, a submitted
     * `recipient_type` (validated against the role's allow-list by the
     * FormRequest) wins; omitted, it falls back to the role's default, same
     * as before 0090.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data, CommissionConfiguration $configuration): self
    {
        // Spec 0306 D-1: a rule that is (or becomes) non-SUPPLIER keeps no direction.
        $role = isset($data['recipient_role'])
            ? CommissionRecipientRole::from($data['recipient_role'])
            : $configuration->recipient_role;

        if ($role !== CommissionRecipientRole::Supplier && (isset($data['recipient_role']) || array_key_exists('supplier_commission_direction', $data))) {
            $data['supplier_commission_direction'] = null;
        }

        if (array_key_exists('recipient_id', $data)) {
            $recipientId = $data['recipient_id'] === null ? null : (int) $data['recipient_id'];
            $data['recipient_id'] = $recipientId;
            $data['recipient_type'] = $recipientId === null ? null : ($data['recipient_type'] ?? $role->recipientType());
        }

        $categoryIds = array_key_exists('product_category_ids', $data) ? CreateCommissionConfigurationData::ids($data['product_category_ids']) : null;
        $productIds = array_key_exists('product_ids', $data) ? CreateCommissionConfigurationData::ids($data['product_ids']) : null;
        unset($data['product_category_ids'], $data['product_ids']);

        // Spec 0430 D-2: changing the scope empties the list of the scope left behind.
        if (isset($data['application_scope'])) {
            $scope = CommissionApplicationScope::from($data['application_scope']);
            $categoryIds = $scope === CommissionApplicationScope::ProductCategory ? $categoryIds : [];
            $productIds = $scope === CommissionApplicationScope::Product ? $productIds : [];
        }

        return new self($data, $categoryIds, $productIds);
    }
}
