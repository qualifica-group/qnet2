<?php

// Plain-text bodies are not HTML: an entity-escaped link (`&amp;email=`) is
// copied verbatim by text-only clients and drops the `email` query parameter.

$url = 'http://localhost:5173/reset-password?token=abc&email=jane%40example.com';

it('keeps the reset link query string unescaped in the plain-text body', function () use ($url) {
    $body = view('emails.reset-password-plain', [
        'name' => 'Jane',
        'url' => $url,
        'expireMinutes' => 60,
    ])->render();

    expect($body)->toContain($url)->not->toContain('&amp;');
});

it('keeps the welcome link query string unescaped in the plain-text body', function () use ($url) {
    $body = view('emails.welcome-user-plain', [
        'appName' => 'QNet',
        'name' => 'Jane',
        'isInvite' => true,
        'url' => $url,
        'expireHours' => 48,
    ])->render();

    expect($body)->toContain($url)->not->toContain('&amp;');
});
