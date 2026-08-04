<?php

use App\Mail\StagingMailRedirector;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

/**
 * Applies the redirect the same way AppServiceProvider::boot() does, on an
 * array transport so the delivered envelope can be inspected. The default
 * mailer must be switched BEFORE handle() runs: alwaysTo() resolves and caches
 * the default mailer instance, which is the one the send then reuses.
 */
function applyStagingRedirect(?string $recipient): void
{
    config([
        'mail.default' => 'array',
        'mail.always_to' => $recipient,
    ]);

    app(StagingMailRedirector::class)->handle();
}

/**
 * @return array<int, Address>
 */
function deliveredTo(): array
{
    /** @var Email $email */
    $email = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage();

    return $email->getTo();
}

it('funnels a message to the configured mailbox and drops the real recipients', function () {
    applyStagingRedirect('staging-inbox@example.com');

    Mail::raw('body', function ($message) {
        $message->to('real@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('probe');
    });

    /** @var Email $email */
    $email = Mail::getSymfonyTransport()->messages()->last()->getOriginalMessage();

    expect(array_map(fn (Address $a) => $a->getAddress(), $email->getTo()))
        ->toBe(['staging-inbox@example.com'])
        ->and($email->getCc())->toBeEmpty()
        ->and($email->getBcc())->toBeEmpty();
});

it('redirects mail sent through the notification channel', function () {
    applyStagingRedirect('staging-inbox@example.com');

    $user = User::factory()->create(['email' => 'real@example.com']);
    $user->notify(new ResetPasswordNotification('the-token'));

    expect(array_map(fn (Address $a) => $a->getAddress(), deliveredTo()))
        ->toBe(['staging-inbox@example.com']);
});

it('leaves recipients untouched when no redirect address is configured', function () {
    applyStagingRedirect(null);

    Mail::raw('body', fn ($message) => $message->to('real@example.com')->subject('probe'));

    expect(array_map(fn (Address $a) => $a->getAddress(), deliveredTo()))
        ->toBe(['real@example.com']);
});

it('treats a blank address as no redirect at all', function () {
    applyStagingRedirect('   ');

    Mail::raw('body', fn ($message) => $message->to('real@example.com')->subject('probe'));

    expect(array_map(fn (Address $a) => $a->getAddress(), deliveredTo()))
        ->toBe(['real@example.com']);
});

it('never redirects in production even when the address is set', function () {
    app()->detectEnvironment(fn () => 'production');

    applyStagingRedirect('staging-inbox@example.com');

    Mail::raw('body', fn ($message) => $message->to('real@example.com')->subject('probe'));

    expect(array_map(fn (Address $a) => $a->getAddress(), deliveredTo()))
        ->toBe(['real@example.com']);
});
