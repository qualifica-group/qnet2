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

// REQUIREMENT CHANGED (spec 0355): the welcome email carries a generated password and a login link.
it('keeps the welcome link and the password unescaped in the plain-text body', function () {
    $body = view('emails.welcome-user-plain', [
        'appName' => 'QNet',
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'password' => 'Ab3dEf6hJk9m',
        'url' => 'http://localhost:5173/login',
    ])->render();

    expect($body)->toContain('http://localhost:5173/login')->toContain('Ab3dEf6hJk9m')->not->toContain('&amp;');
});
