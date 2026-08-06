<?php

use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * quotes.payment_method_id (user directive 2026-07-30): the schema, the
 * write path on create/update, the explicit-null clear on PATCH, the
 * additive GET shape, the field-permission ceiling, the "no inheritance, no
 * default" rule (unlike layout_id) and the delete-guard this consumer adds
 * to PaymentMethodService.
 */
uses(RefreshDatabase::class);

if (! function_exists('quotePaymentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quotePaymentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
            Permission::findOrCreate("payment-methods.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('quotePaymentNewStatus')) {
    function quotePaymentNewStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

// ---------------------------------------------------------------------------
// Schema
// ---------------------------------------------------------------------------

it('quotes.payment_method_id is nullable with a FK to payment_methods', function () {
    $method = PaymentMethod::factory()->create();
    $quote = Quote::factory()->create(['payment_method_id' => $method->id]);

    expect($quote->fresh()->payment_method_id)->toBe($method->id);

    $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'payment_method_id' => $method->id]);
});

it('deleting a PaymentMethod at the DB level nulls payment_method_id and the quote survives', function () {
    $method = PaymentMethod::factory()->create();
    $quote = Quote::factory()->create(['payment_method_id' => $method->id]);

    DB::table('payment_methods')->where('id', $method->id)->delete();

    expect($quote->fresh())
        ->not->toBeNull()
        ->payment_method_id->toBeNull();
});

// ---------------------------------------------------------------------------
// Create
// ---------------------------------------------------------------------------

it('create persists a submitted payment_method_id', function () {
    quotePaymentNewStatus();
    $method = PaymentMethod::factory()->create(['name' => 'Bonifico 30gg']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quotePaymentUserWith(['quotes.create']));

    $this->postJson('/api/quotes', [
        'title' => 'Con pagamento',
        'opportunity_id' => $opportunity->id,
        'payment_method_id' => $method->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.payment_method_id', $method->id)
        ->assertJsonPath('data.payment_method.id', $method->id)
        ->assertJsonPath('data.payment_method.name', 'Bonifico 30gg');
});

it('create without payment_method_id leaves it null: no default, no inheritance', function () {
    quotePaymentNewStatus();
    // A row that would be "the first"/"the default" if any such concept
    // existed for payment methods (it does not, unlike layout_id).
    PaymentMethod::factory()->create(['sort_order' => 1]);
    $commercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $commercial->id]);
    Sanctum::actingAs(quotePaymentUserWith(['quotes.create']));

    $this->postJson('/api/quotes', [
        'title' => 'Senza pagamento',
        'opportunity_id' => $opportunity->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.payment_method_id', null)
        ->assertJsonPath('data.payment_method', null)
        // The pre-existing snapshot inheritance is untouched.
        ->assertJsonPath('data.commercial_id', $commercial->id);
});

it('a nonexistent payment_method_id is rejected with 422 on create', function () {
    quotePaymentNewStatus();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quotePaymentUserWith(['quotes.create']));

    $this->postJson('/api/quotes', [
        'title' => 'Pagamento inesistente',
        'opportunity_id' => $opportunity->id,
        'payment_method_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_method_id']);

    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

it('PATCH sets payment_method_id on a quote that had none', function () {
    $method = PaymentMethod::factory()->create();
    $quote = Quote::factory()->create(['payment_method_id' => null]);
    Sanctum::actingAs(quotePaymentUserWith(['quotes.view', 'quotes.update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['payment_method_id' => $method->id])
        ->assertOk()
        ->assertJsonPath('data.payment_method_id', $method->id);
});

it('PATCH {payment_method_id: null} clears a previously set payment method', function () {
    $method = PaymentMethod::factory()->create();
    $quote = Quote::factory()->create(['payment_method_id' => $method->id]);
    Sanctum::actingAs(quotePaymentUserWith(['quotes.view', 'quotes.update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['payment_method_id' => null])
        ->assertOk()
        ->assertJsonPath('data.payment_method_id', null);
});

it('PATCH without payment_method_id leaves the persisted one untouched', function () {
    $method = PaymentMethod::factory()->create();
    $quote = Quote::factory()->create(['payment_method_id' => $method->id]);
    Sanctum::actingAs(quotePaymentUserWith(['quotes.view', 'quotes.update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Titolo aggiornato'])
        ->assertOk()
        ->assertJsonPath('data.payment_method_id', $method->id)
        ->assertJsonPath('data.title', 'Titolo aggiornato');
});

// ---------------------------------------------------------------------------
// GET show — additive shape
// ---------------------------------------------------------------------------

it('GET show exposes payment_method_id/payment_method without altering existing keys', function () {
    $method = PaymentMethod::factory()->create(['name' => 'Rimessa diretta']);
    $withMethod = Quote::factory()->create(['payment_method_id' => $method->id]);
    $withoutMethod = Quote::factory()->create(['payment_method_id' => null]);
    Sanctum::actingAs(quotePaymentUserWith(['quotes.view']));

    $this->getJson("/api/quotes/{$withMethod->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_method_id', $method->id)
        ->assertJsonPath('data.payment_method.name', 'Rimessa diretta')
        ->assertJsonPath('data.code', $withMethod->code)
        ->assertJsonPath('data.title', $withMethod->title);

    $this->getJson("/api/quotes/{$withoutMethod->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_method_id', null)
        ->assertJsonPath('data.payment_method', null);
});

// ---------------------------------------------------------------------------
// Field permissions
// ---------------------------------------------------------------------------

it('GET /api/meta/quotes exposes payment_method_id with the expected ceiling', function () {
    Sanctum::actingAs(quotePaymentUserWith(['quotes.viewAny', 'quotes.create']));

    $fields = $this->getJson('/api/meta/quotes')->assertOk()->json('permissions.fields');

    expect($fields)->toHaveKey('payment_method_id')
        ->and($fields['payment_method_id']['visible'])->toBeTrue()
        ->and($fields['payment_method_id']['editable'])->toBeTrue()
        ->and($fields['payment_method_id']['required'])->toBeFalse();
});

it('PATCH changing payment_method_id is 422 when the role denies it via role_field_permissions', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $role = Role::create(['name' => 'quote-payment-locked']);
    $role->givePermissionTo(['quotes.view', 'quotes.update']);
    $role->fieldPermissions()->create([
        'resource' => 'quotes',
        'field' => 'payment_method_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $original = PaymentMethod::factory()->create();
    $another = PaymentMethod::factory()->create();
    $target = Quote::factory()->create(['payment_method_id' => $original->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$target->id}", ['payment_method_id' => $another->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('payment_method_id');

    expect($target->fresh()->payment_method_id)->toBe($original->id);
});

// ---------------------------------------------------------------------------
// Delete-guard on the lookup (quotes is its first consumer)
// ---------------------------------------------------------------------------

it('deleting a payment method referenced by a quote is rejected with 409', function () {
    $method = PaymentMethod::factory()->create();
    Quote::factory()->create(['payment_method_id' => $method->id]);
    Sanctum::actingAs(quotePaymentUserWith(['payment-methods.view', 'payment-methods.delete']));

    $this->deleteJson("/api/payment-methods/{$method->id}")->assertStatus(409);

    $this->assertDatabaseHas('payment_methods', ['id' => $method->id]);
});

it('deleting an unreferenced payment method still succeeds', function () {
    $method = PaymentMethod::factory()->create();
    Sanctum::actingAs(quotePaymentUserWith(['payment-methods.view', 'payment-methods.delete']));

    $this->deleteJson("/api/payment-methods/{$method->id}")->assertNoContent();

    $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
});
