<?php

use App\DataObjects\WorkOrderEmails\OutboundEmailDraftData;
use App\Enums\EmailTemplateModule;
use App\Enums\OutboundEmailPurpose;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\EmailOwnerRegistry;
use App\Services\OutboundEmails\OutboundEmailService;
use App\Services\OutboundEmails\Owners\WorkOrderEmailOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| EmailOwner registry + outbound_emails.purpose (spec 0195, D-10/D-11)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

it('resolves the work order owner by model and by template module', function () {
    $registry = app(EmailOwnerRegistry::class);

    expect($registry->for(new WorkOrder))->toBeInstanceOf(WorkOrderEmailOwner::class)
        ->and($registry->forModule(EmailTemplateModule::WorkOrders))->toBeInstanceOf(WorkOrderEmailOwner::class)
        ->and($registry->forAlias('work_order')->viewAbility())->toBe('viewEmails')
        ->and($registry->forAlias('work_order')->sendAbility())->toBe('sendEmail');
});

it('throws for an unregistered owner alias', function () {
    app(EmailOwnerRegistry::class)->forAlias('unknown');
})->throws(InvalidArgumentException::class);

it('stores the purpose on a draft and leaves it null by default', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = User::factory()->create();
    $service = app(OutboundEmailService::class);

    $plain = $service->createDraft($workOrder, $actor, new OutboundEmailDraftData);
    $reminder = $service->createDraft($workOrder, $actor, new OutboundEmailDraftData, OutboundEmailPurpose::Reminder);

    expect($plain->purpose)->toBeNull()
        ->and($reminder->purpose)->toBe(OutboundEmailPurpose::Reminder);
});
