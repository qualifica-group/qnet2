<?php

declare(strict_types=1);

use App\CustomFields\Exceptions\UnknownFieldTypeException;
use App\CustomFields\FieldTypeRegistry;
use App\CustomFields\Types\BooleanFieldType;
use App\CustomFields\Types\ColorFieldType;
use App\CustomFields\Types\DateFieldType;
use App\CustomFields\Types\DateTimeFieldType;
use App\CustomFields\Types\DecimalFieldType;
use App\CustomFields\Types\EmailFieldType;
use App\CustomFields\Types\EnumFieldType;
use App\CustomFields\Types\FieldTypeHandler;
use App\CustomFields\Types\IntegerFieldType;
use App\CustomFields\Types\RelationFieldType;
use App\CustomFields\Types\TextareaFieldType;
use App\CustomFields\Types\TextFieldType;
use App\CustomFields\Types\TimeFieldType;
use App\CustomFields\Types\UrlFieldType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// RelationFieldType depends on CustomFieldEntityRegistry, which resolves
// TableRegistry/AuthorizationRegistry definitions that touch the DB.
uses(TestCase::class, RefreshDatabase::class);

// AC-003: FieldTypeRegistry resolves each registered type from config.
it('resolves every registered type from config/custom-fields.php', function (string $type, string $class): void {
    $handler = app(FieldTypeRegistry::class)->resolve($type);

    expect($handler)->toBeInstanceOf(FieldTypeHandler::class)
        ->and($handler)->toBeInstanceOf($class)
        ->and($handler->key())->toBe($type);
})->with([
    'text' => ['text', TextFieldType::class],
    'textarea' => ['textarea', TextareaFieldType::class],
    'integer' => ['integer', IntegerFieldType::class],
    'decimal' => ['decimal', DecimalFieldType::class],
    'boolean' => ['boolean', BooleanFieldType::class],
    'enum' => ['enum', EnumFieldType::class],
    'relation' => ['relation', RelationFieldType::class],
    'date' => ['date', DateFieldType::class],
    'datetime' => ['datetime', DateTimeFieldType::class],
    'time' => ['time', TimeFieldType::class],
    'email' => ['email', EmailFieldType::class],
    'url' => ['url', UrlFieldType::class],
    'color' => ['color', ColorFieldType::class],
]);

it('lists all registered types via has()/all()', function (): void {
    $registry = app(FieldTypeRegistry::class);

    expect($registry->has('text'))->toBeTrue()
        ->and($registry->has('unknown'))->toBeFalse()
        ->and($registry->all())->toBe([
            'text', 'textarea', 'integer', 'decimal', 'boolean', 'enum', 'relation',
            'date', 'datetime', 'time', 'email', 'url', 'color', 'table',
        ]);
});

// AC-003: unknown type → exception.
it('throws UnknownFieldTypeException for an unregistered type', function (): void {
    app(FieldTypeRegistry::class)->resolve('does-not-exist');
})->throws(UnknownFieldTypeException::class, 'Unknown custom field type [does-not-exist].');

// AC-003: storageType/columnType/filterType coherent with the spec matrix.
it('declares text handler triple string/text/text', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('text');

    expect($handler->storageType())->toBe('string')
        ->and($handler->columnType())->toBe('text')
        ->and($handler->filterType())->toBe('text');
});

it('declares integer handler triple integer/number/number', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('integer');

    expect($handler->storageType())->toBe('integer')
        ->and($handler->columnType())->toBe('number')
        ->and($handler->filterType())->toBe('number');
});

it('declares decimal handler columnType/filterType number/number', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('decimal');

    expect($handler->columnType())->toBe('number')
        ->and($handler->filterType())->toBe('number');
});

it('declares boolean handler triple boolean/boolean/boolean', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('boolean');

    expect($handler->storageType())->toBe('boolean')
        ->and($handler->columnType())->toBe('boolean')
        ->and($handler->filterType())->toBe('boolean');
});

it('declares enum handler triple json|string/enum/set', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('enum');

    expect(in_array($handler->storageType(), ['json', 'string'], true))->toBeTrue()
        ->and($handler->columnType())->toBe('enum')
        ->and($handler->filterType())->toBe('set');
});

it('declares relation handler triple json/text/set', function (): void {
    $handler = app(FieldTypeRegistry::class)->resolve('relation');

    expect($handler->storageType())->toBe('json')
        ->and($handler->columnType())->toBe('text')
        ->and($handler->filterType())->toBe('set');
});

// The scalar-string family (date/datetime/time/email/url/color) all share the
// string/text/text triple — they differ only in accepted input format.
it('declares the scalar-string family triple string/text/text', function (string $type): void {
    $handler = app(FieldTypeRegistry::class)->resolve($type);

    expect($handler->storageType())->toBe('string')
        ->and($handler->columnType())->toBe('text')
        ->and($handler->filterType())->toBe('text');
})->with(['date', 'datetime', 'time', 'email', 'url', 'color']);
