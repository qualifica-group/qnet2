<?php

declare(strict_types=1);

use App\Enums\DocumentLayoutModule;
use Tests\TestCase;

uses(TestCase::class);

// spec 0069 — DocumentLayoutModule presentation metadata.
//
// Regression guard for a real defect found while wiring the `module` advanced
// filter: `HasMeta::label()` resolves the `#[Label]` string with `__()`, and a
// bare English label whose text matches a translation FILE name resolves to the
// GROUP instead of a line. `#[Label('Quotes')]` returned the whole
// `lang/{it,en}/quotes.php` array and blew up `label(): string` with a
// TypeError, turning every `GET /api/config` call into a 500 — the enum is in
// `config/config.php` `form_enums`, so the failure was global, not local to
// this module. The label is therefore the translation KEY, and these tests
// pin that contract for both locales.

it('resolves the module label to a localized string, never a translation group', function (): void {
    app()->setLocale('en');
    expect(DocumentLayoutModule::Quotes->label())->toBe('Quotes');

    app()->setLocale('it');
    expect(DocumentLayoutModule::Quotes->label())->toBe('Preventivi');
});

it('serializes every case to EnumMeta with a string label', function (): void {
    foreach (DocumentLayoutModule::options() as $meta) {
        expect($meta->label)->toBeString()->not->toBeEmpty();
        expect($meta->value)->toBeString()->not->toBeEmpty();
    }
});

it('keeps the #[Label] key and labelKey() in sync', function (): void {
    // Both must point at the same lang line: the enum options feed the grid
    // filter, `labelKey()` feeds `DocumentLayoutResource::module_label`. If they
    // drift, the same module shows two different names in the same screen.
    app()->setLocale('it');

    expect(DocumentLayoutModule::Quotes->label())
        ->toBe(__(DocumentLayoutModule::Quotes->labelKey()));
});

it('exposes the module in the client enum allowlist', function (): void {
    expect(config('config.form_enums'))
        ->toHaveKey('document_layout_module', DocumentLayoutModule::class);
});
