<?php

use App\Models\CommissionConfiguration;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\RewardType;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Foreign keys with no relation on the subject model
    |--------------------------------------------------------------------------
    |
    | [subject alias][field] => related model class, for fields whose label
    | ForeignKeyLabelResolver cannot infer from a BelongsTo relation of the
    | subject. Request management writes EXPLICIT Opportunity entries carrying
    | Quote-level fields (D-9); `product_lines`, `rewards` and `manager_slots`
    | carry a LIST of ids (a null manager slot is an empty position).
    |
    */
    'opportunity' => [
        'quote_workflow_status_id' => QuoteWorkflowStatus::class,
        'operator_id' => User::class,
        'manager_slots' => User::class,
        'product_lines' => ProductCategory::class,
        'rewards' => RewardType::class,
    ],
    // Spec 0372, D-7: people lists logged by PeopleChangeLog (user ids, null = empty slot).
    'quote' => [
        'manager_slots' => User::class,
    ],
    'work_order' => [
        'supervisors' => User::class,
        'participants' => User::class,
    ],
    'quote_line_commission' => [
        'commission_configuration_id' => CommissionConfiguration::class,
    ],
    // Logged by ContractReactivator when a reopening clears the validation stamp.
    'contract' => [
        'validated_by' => User::class,
    ],

];
