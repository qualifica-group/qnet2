<?php

use App\Support\Geo\GeoNameLocalizer;

it('translates anglicized Italy names to Italian and passes everything else through', function () {
    expect(GeoNameLocalizer::toItalian('Italy'))->toBe('Italia')
        ->and(GeoNameLocalizer::toItalian('Lombardy'))->toBe('Lombardia')
        ->and(GeoNameLocalizer::toItalian('Apulia'))->toBe('Puglia')
        ->and(GeoNameLocalizer::toItalian('Naples'))->toBe('Napoli')
        ->and(GeoNameLocalizer::toItalian('South Tyrol'))->toBe('Bolzano')
        // Already-Italian dataset names pass through untouched.
        ->and(GeoNameLocalizer::toItalian('Campania'))->toBe('Campania')
        ->and(GeoNameLocalizer::toItalian('Avellino'))->toBe('Avellino')
        // Foreign names are left alone (Italy-only scope).
        ->and(GeoNameLocalizer::toItalian('France'))->toBe('France')
        ->and(GeoNameLocalizer::toItalian(null))->toBeNull();
});

it('reverses an Italian display name back to the English DB name (lossless bijection)', function () {
    expect(GeoNameLocalizer::toEnglish('Napoli'))->toBe('Naples')
        ->and(GeoNameLocalizer::toEnglish('Lombardia'))->toBe('Lombardy')
        ->and(GeoNameLocalizer::toEnglish('Italia'))->toBe('Italy')
        ->and(GeoNameLocalizer::toEnglish('Bolzano'))->toBe('South Tyrol')
        // Passthrough for names that are not deltas.
        ->and(GeoNameLocalizer::toEnglish('Campania'))->toBe('Campania');
});

it('round-trips every mapped name through toItalian then toEnglish', function () {
    foreach (['Italy', 'Sicily', 'Sardinia', 'South Sardinia', 'Trentino', 'Trentino-South Tyrol', 'Milan', 'Rome'] as $english) {
        expect(GeoNameLocalizer::toEnglish(GeoNameLocalizer::toItalian($english)))->toBe($english);
    }
});

it('reverses a list of set-filter values, preserving order and passthrough', function () {
    expect(GeoNameLocalizer::toEnglishValues(['Napoli', 'Campania', 'Milano']))
        ->toBe(['Naples', 'Campania', 'Milan']);
});

it('finds English names whose Italian display matches a quick-search needle', function () {
    expect(GeoNameLocalizer::englishNamesMatching('napo'))->toBe(['Naples'])
        ->and(GeoNameLocalizer::englishNamesMatching('SARDEGNA'))->toContain('Sardinia')
        ->and(GeoNameLocalizer::englishNamesMatching('zzz'))->toBe([])
        ->and(GeoNameLocalizer::englishNamesMatching('  '))->toBe([]);
});

it('finds English names whose Italian display STARTS WITH a prefix needle', function () {
    expect(GeoNameLocalizer::englishNamesStartingWith('napoli'))->toBe(['Naples'])
        ->and(GeoNameLocalizer::englishNamesStartingWith('NAPO'))->toBe(['Naples'])
        ->and(GeoNameLocalizer::englishNamesStartingWith(' roma '))->toBe(['Rome'])
        ->and(GeoNameLocalizer::englishNamesStartingWith('zzz'))->toBe([])
        ->and(GeoNameLocalizer::englishNamesStartingWith('  '))->toBe([]);
});

it('does not treat a mid-word substring as a prefix, unlike the contains variant', function () {
    // "poli" sits inside "Napoli" but is not a prefix: the cascade select is a
    // prefix lookup, so only englishNamesMatching() may return it.
    expect(GeoNameLocalizer::englishNamesStartingWith('poli'))->toBe([])
        ->and(GeoNameLocalizer::englishNamesMatching('poli'))->toBe(['Naples']);
});
