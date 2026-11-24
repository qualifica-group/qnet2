<?php

declare(strict_types=1);

use App\Enums\DocumentChecklistSyncStatus;
use App\Models\ArchivioSmartSetting;
use App\Models\Contact;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistCategory;
use App\Models\DocumentRequest;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

const DR_BASE_URL = 'https://archivio.test/api/v1';
const DR_TOKEN = 'as_secret_token_987654321';
const DR_WEBHOOK_SECRET = 'whsec_test_secret_0123456789';

if (! function_exists('documentRequestUserWith')) {
    /**
     * A user with the given document-requests abilities and `work-orders.view` on every work order.
     *
     * @param  array<int, string>  $abilities
     */
    function documentRequestUserWith(array $abilities, bool $canViewWorkOrders = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("document-requests.{$ability}");
        }
        foreach (['view', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();
        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-requests.{$ability}");
        }
        if ($canViewWorkOrders) {
            $user->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);
        }

        return $user;
    }
}

if (! function_exists('documentRequestConnection')) {
    function documentRequestConnection(?string $webhookSecret = null): ArchivioSmartSetting
    {
        return ArchivioSmartSetting::factory()->create([
            'base_url' => DR_BASE_URL.'/',
            'token' => DR_TOKEN,
            'webhook_secret' => $webhookSecret,
        ]);
    }
}

if (! function_exists('documentRequestChecklist')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function documentRequestChecklist(array $attributes = []): DocumentChecklist
    {
        return DocumentChecklist::factory()->create([
            'sync_status' => DocumentChecklistSyncStatus::Synced,
            ...$attributes,
        ]);
    }
}

if (! function_exists('documentRequestCategory')) {
    function documentRequestCategory(string $name = 'Antiriciclaggio'): DocumentChecklistCategory
    {
        return DocumentChecklistCategory::factory()->create(['name' => $name]);
    }
}

if (! function_exists('documentRequestWorkOrder')) {
    /**
     * A work order whose customer holds a primary email (or none).
     */
    function documentRequestWorkOrder(?string $primaryEmail = 'cliente@example.test'): WorkOrder
    {
        $quote = documentRequestQuote($primaryEmail);

        return WorkOrder::factory()->create(['quote_id' => $quote->id]);
    }
}

if (! function_exists('documentRequestQuote')) {
    function documentRequestQuote(?string $primaryEmail, string $name = 'Alfa Srl'): Quote
    {
        $registry = Registry::factory()->create(['name' => $name]);
        $card = PersonalData::factory()->company()->for($registry, 'personable')->create();

        if ($primaryEmail !== null) {
            Contact::factory()->email()->primary()->for($card, 'contactable')->create(['value' => $primaryEmail]);
            Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'secondaria@example.test']);
        }

        return Quote::factory()->create(['opportunity_id' => Opportunity::factory()->create(['registry_id' => $registry->id])->id]);
    }
}

if (! function_exists('documentRequestMultiQuoteWorkOrder')) {
    /**
     * A multi-offer work order (`quote_id` null): one customer per offer, in the given order.
     *
     * @param  array<int, string|null>  $emails
     */
    function documentRequestMultiQuoteWorkOrder(array $emails): WorkOrder
    {
        $workOrder = WorkOrder::factory()->multiQuote()->create();

        foreach ($emails as $index => $email) {
            $quote = documentRequestQuote($email, 'Cliente '.$index);
            $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $quote->id])->id);
        }

        return $workOrder;
    }
}

if (! function_exists('documentRequestRemote')) {
    /**
     * An ArchivioSmart request representation (the `data` object of a response).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function documentRequestRemote(array $overrides = []): array
    {
        return array_merge([
            'status' => 'sent',
            'title' => 'Apertura pratica',
            'items_count' => 2,
            'items_uploaded_count' => 0,
            'items_satisfied_count' => 0,
            'sections' => [['name' => 'Identita', 'description' => null]],
            'items' => [
                ['id' => 11, 'section_index' => 0, 'name' => 'Documento A', 'description' => null, 'is_required' => true, 'min_files' => 1, 'max_files' => 1, 'status' => 'pending', 'status_reason' => null, 'latest_validation' => null, 'documents' => []],
                ['id' => 12, 'section_index' => 0, 'name' => 'Documento B', 'description' => null, 'is_required' => false, 'min_files' => 1, 'max_files' => 2, 'status' => 'pending', 'status_reason' => null, 'latest_validation' => null, 'documents' => [['id' => 1, 'file_name' => 'a.pdf']]],
            ],
            'sent_at' => '2026-12-01T10:00:00+00:00',
            'last_reminded_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'expired_at' => null,
            'link_expires_at' => '2026-12-31T10:00:00+00:00',
            'updated_at' => '2026-12-01T10:00:00+00:00',
        ], $overrides);
    }
}

if (! function_exists('documentRequestFor')) {
    /**
     * A request already answered by ArchivioSmart (delivered, status sent, snapshot filled).
     *
     * @param  array<string, mixed>  $attributes
     */
    function documentRequestFor(WorkOrder $workOrder, array $attributes = []): DocumentRequest
    {
        $remote = documentRequestRemote();

        return DocumentRequest::factory()->create([
            'work_order_id' => $workOrder->id,
            'status' => 'sent',
            'title' => $remote['title'],
            'items_count' => 2,
            'snapshot' => ['sections' => $remote['sections'], 'items' => $remote['items']],
            'remote_updated_at' => '2026-12-01 10:00:00',
            'delivery_status' => 'delivered',
            ...$attributes,
        ]);
    }
}

if (! function_exists('postArchivioSmartWebhook')) {
    /**
     * Signs the body like ArchivioSmart does (hex HMAC-SHA256 of the raw body) and posts it.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers  overrides, `Signature` included
     */
    function postArchivioSmartWebhook(object $test, array $body, string $event, string $eventId, array $headers = [], string $secret = DR_WEBHOOK_SECRET): TestResponse
    {
        $raw = json_encode($body, JSON_THROW_ON_ERROR);

        return $test->call('POST', '/api/webhooks/archivio-smart', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SIGNATURE' => $headers['Signature'] ?? hash_hmac('sha256', $raw, $secret),
            'HTTP_X_ARCHIVIOSMART_EVENT' => $event,
            'HTTP_X_ARCHIVIOSMART_EVENT_ID' => $eventId,
            'HTTP_X_ARCHIVIOSMART_TIMESTAMP' => (string) time(),
        ], $raw);
    }
}
