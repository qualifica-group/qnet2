<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldRequestBag;
use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\TableRowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0390 AC-007/AC-008/AC-009: the row event on an Offerta, on a custom
// field, and the default email.
uses(RefreshDatabase::class);

it('AC-008: a row switched on in an Offerta tells its Supervisore, Gestori Account and Operatore', function (): void {
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }
    $actor = tap(User::factory()->create())->givePermissionTo(['quotes.create', 'quotes.update', 'quotes.view', 'quotes.viewAll']);
    Sanctum::actingAs($actor);

    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $attribute = Attribute::factory()->ofType('table')->create(['code' => 'inspections', 'config' => notifyingInspectionConfig()]);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value]);
    $product = Product::factory()->create(['category_id' => $category->id]);

    $id = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => Opportunity::factory()->create()->id,
        'offer_lines' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
    ])->assertCreated()->json('data.id');

    [$supervisor, $manager, $operator] = User::factory()->count(3)->create();
    $quote = Quote::query()->findOrFail($id);
    $quote->forceFill(['supervisor_id' => $supervisor->id, 'operator_id' => $operator->id])->saveQuietly();
    $quote->managers()->attach($manager->id, ['position' => 1]);
    Notification::fake();

    $this->patchJson("/api/quotes/{$id}", ['attribute_values' => ['inspections' => ['rows' => [
        ['inspection_date' => '2026-12-01', 'alert_sent' => true],
    ]]]])->assertOk();

    Notification::assertSentTo([$supervisor, $manager, $operator], TableRowNotification::class);
    Notification::assertNotSentTo($actor, TableRowNotification::class);
});

it('AC-007: a row added to a table custom field tells the chosen users, once', function (): void {
    $recipient = User::factory()->create();
    tableCustomField(['notify' => [
        'trigger' => null, 'email_template_id' => null, 'record_team' => true,
        'user_ids' => [$recipient->id], 'role_ids' => [], 'emails' => [],
    ]]);
    Sanctum::actingAs(User::factory()->create());
    $company = Company::factory()->create();
    Notification::fake();

    app(CustomFieldRequestBag::class)->set(['inspections' => ['rows' => [['inspection_date' => '2026-12-01']]]]);
    $company->save();
    $stored = $company->fresh()->custom_fields['inspections'];
    app(CustomFieldRequestBag::class)->set(['inspections' => $stored]);
    $company->save();

    Notification::assertSentToTimes($recipient, TableRowNotification::class, 1);
});

it('AC-009: the default email names the field, the row values and the record, without a link it cannot offer', function (): void {
    $recipient = User::factory()->create(['name' => 'Anna']);
    $notification = new TableRowNotification(
        fieldName: 'Verifiche',
        triggerLabel: 'Avviso',
        recordLabel: 'ACME',
        target: null,
        recordId: 7,
        actorName: 'Mario',
        email: null,
        rowDetails: ['Data' => '01/12/2026', 'Avviso' => 'notifications.values.yes'],
        recordDetails: ['notifications.fields.name' => 'ACME'],
    );

    $mail = $notification->toMail($recipient);
    $html = (string) $mail->render();
    $data = $notification->toArray($recipient);

    expect($mail->subject)->toBe('Verifiche - ACME')
        ->and($mail->actionUrl)->toBeNull()
        ->and($html)->toContain('Anna')
        ->and($html)->toContain('01/12/2026')
        ->and($html)->toContain(__('notifications.values.yes'))
        ->and($data['message'])->toBe(__(':actor turned on ":column" on a row of ":field" on :record.', [
            'actor' => 'Mario', 'column' => 'Avviso', 'field' => 'Verifiche', 'record' => 'ACME',
        ]))
        ->and($notification->via($recipient))->toContain('database');
});

it('AC-009: a free address gets the email only, with the row values but not the record card', function (): void {
    $notification = new TableRowNotification(
        fieldName: 'Verifiche', triggerLabel: null, recordLabel: 'ACME', target: null, recordId: 7, actorName: 'Mario',
        email: null, rowDetails: ['Data' => '01/12/2026'], recordDetails: ['notifications.fields.supervisors' => 'Internal Person'],
    );
    $notifiable = Notification::route('mail', 'audit@example.com');

    $html = (string) $notification->toMail($notifiable)->render();

    expect($notification->via($notifiable))->toBe(['mail'])
        ->and($html)->toContain('01/12/2026')
        ->and($html)->not->toContain('Internal Person');
});
