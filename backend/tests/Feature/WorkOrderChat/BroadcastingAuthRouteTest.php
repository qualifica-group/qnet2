<?php

it('registers the broadcasting auth route under /api with sanctum', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/broadcasting/auth');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->gatherMiddleware())->toContain('auth:sanctum');
});

it('rejects unauthenticated channel authorization', function () {
    $this->postJson('/api/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-work-orders.1.chat',
    ])->assertUnauthorized();
});
