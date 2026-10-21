<?php

declare(strict_types=1);

use App\RequestManagement\AttributeSectionVisibility;

// Spec 0288, D-3/D-6 (AC-005): the single server-side rule deciding which
// conditional sections are hidden. Pure logic, no database.

/**
 * @param  array<int, string>  $codes
 * @return array<string, mixed>
 */
function visibilitySection(array $codes, ?string $control = null): array
{
    return [
        'id' => 's-'.implode('-', $codes),
        'visible_when' => $control === null ? null : ['attribute_code' => $control],
        'rows' => array_map(
            static fn (string $code): array => ['id' => "r-{$code}", 'items' => [['attribute_code' => $code, 'width' => 'full']]],
            $codes,
        ),
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $sections
 * @param  array<string, mixed>  $values
 * @return array<int, string>
 */
function hiddenFor(array $sections, array $values): array
{
    $hidden = (new AttributeSectionVisibility)->hiddenCodes(['sections' => $sections], $values);
    sort($hidden);

    return $hidden;
}

it('hides the section when the control is false, null or absent', function (mixed $value): void {
    $sections = [visibilitySection(['toggle']), visibilitySection(['extra'], 'toggle')];

    expect(hiddenFor($sections, ['toggle' => $value]))->toBe(['extra']);
})->with([[false], [null], ['0'], [0]]);

it('hides the section when the control is absent from the values', function (): void {
    $sections = [visibilitySection(['toggle']), visibilitySection(['extra'], 'toggle')];

    expect(hiddenFor($sections, []))->toBe(['extra']);
});

it('hides the section when the control is not placed in the layout', function (): void {
    $sections = [visibilitySection(['toggle']), visibilitySection(['extra'], 'ghost')];

    expect(hiddenFor($sections, ['toggle' => true, 'ghost' => true]))->toBe(['extra']);
});

it('shows the section when the control is active, including unnormalized truthy input', function (mixed $value): void {
    $sections = [visibilitySection(['toggle']), visibilitySection(['extra'], 'toggle')];

    expect(hiddenFor($sections, ['toggle' => $value]))->toBe([]);
})->with([[true], [1], ['1']]);

it('never hides a section without a condition', function (): void {
    expect(hiddenFor([visibilitySection(['a']), visibilitySection(['b'])], []))->toBe([]);
});

it('hides a chained section when its container is hidden, even if its own control is on', function (): void {
    $sections = [
        visibilitySection(['master']),
        visibilitySection(['middle_toggle'], 'master'),
        visibilitySection(['leaf'], 'middle_toggle'),
    ];

    expect(hiddenFor($sections, ['master' => false, 'middle_toggle' => true]))->toBe(['leaf', 'middle_toggle'])
        ->and(hiddenFor($sections, ['master' => true, 'middle_toggle' => true]))->toBe([]);
});

it('hides every section of a cycle', function (): void {
    $sections = [visibilitySection(['a'], 'b'), visibilitySection(['b'], 'a')];

    expect(hiddenFor($sections, ['a' => true, 'b' => true]))->toBe(['a', 'b']);
});

it('hides a section controlled by one of its own codes', function (): void {
    expect(hiddenFor([visibilitySection(['self'], 'self')], ['self' => true]))->toBe(['self']);
});

it('returns nothing for a null layout or one without sections', function (): void {
    $visibility = new AttributeSectionVisibility;

    expect($visibility->hiddenCodes(null, ['x' => true]))->toBe([])
        ->and($visibility->hiddenCodes(['sections' => []], []))->toBe([]);
});

it('mergeAndClear empties hidden codes with a value and keeps visible ones', function (): void {
    $layout = ['sections' => [visibilitySection(['toggle']), visibilitySection(['extra'], 'toggle')]];

    $merged = (new AttributeSectionVisibility)->mergeAndClear(
        $layout,
        ['toggle' => true, 'extra' => 'filled', 'free' => 'kept'],
        ['toggle' => false],
    );

    expect($merged)->toBe(['toggle' => false, 'extra' => null, 'free' => 'kept']);
});

it('mergeAndClear does not invent keys for hidden codes that were never stored', function (): void {
    $layout = ['sections' => [visibilitySection(['toggle']), visibilitySection(['extra'], 'toggle')]];

    expect((new AttributeSectionVisibility)->mergeAndClear($layout, [], ['toggle' => false]))->toBe(['toggle' => false]);
});
