<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is public: returns the deployed version without authentication', function () {
    config(['app.version' => 'f3e2ae8e']);

    $this->getJson('/api/version')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.version', 'f3e2ae8e');
});

it('returns a null version when the deploy identifier is not configured', function () {
    config(['app.version' => null]);

    $this->getJson('/api/version')
        ->assertOk()
        ->assertJsonPath('data.version', null);
});

it('treats a blank deploy identifier as unconfigured', function () {
    config(['app.version' => '   ']);

    $this->getJson('/api/version')
        ->assertOk()
        ->assertJsonPath('data.version', null);
});

it('forbids caching so an intermediary cannot freeze the pre-deploy value', function () {
    config(['app.version' => 'f3e2ae8e']);

    $response = $this->getJson('/api/version')->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});
