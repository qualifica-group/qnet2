<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-008 [rivisto 2026-09-23]: "Notifiche" non e' piu' una voce del menu
// backend-driven (D-4 rivista) - diventa un link fisso nel footer della
// sidebar, di competenza del frontend. La navigazione backend (GET
// navigazione), sul config/navigation.php REALE (nessun override), non
// contiene alcuna voce `notifications`, per nessun utente autenticato.

it('GET /api/navigation: does not contain a notifications item (AC-008, revised)', function () {
    Sanctum::actingAs(User::factory()->create());

    $keys = collect($this->getJson('/api/navigation')->assertOk()->json('data'))->pluck('key')->all();

    expect($keys)->not->toContain('notifications');
});

it('config/navigation/notifications.php no longer exists', function () {
    expect(file_exists(base_path('config/navigation/notifications.php')))->toBeFalse();
});
