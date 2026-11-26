<?php

use App\Services\AiAssistant\Agent\AiAgentInstructions;
use App\Services\AiAssistant\Settings\AiDefaultInstructions;

// REQUIREMENT CHANGED (committente, 26/11/2026): «Anche la visualizzazione dei dati (es tabella) deve
// essere migliorato questi segmenti |---|--| non vanno bene, anche l'argomentazione grammaticale in
// italiano deve essere migliorata.» Il formato dell'uscita del nucleo ora fissa lingua, grammatica e tabelle.

it('the core states the language rules: person language, correct Italian with accents, no jargon, no empty closing', function () {
    $core = AiAgentInstructions::core();

    expect($core)->toContain('language of the person')
        ->and($core)->toContain('città, attività, è')
        ->and($core)->toContain('professional')
        ->and($core)->toContain('calques')
        ->and($core)->toContain('endpoint')
        ->and($core)->toContain('"Fine."')
        ->and($core)->toContain('Spero di esserti stato utile');
});

it('the core states the table rules: GFM, minimum items and attributes, readable headers, right-aligned numbers', function () {
    $core = AiAgentInstructions::core();

    expect($core)->toContain('3 or more')
        ->and($core)->toContain('2 or more attributes')
        ->and($core)->toContain('6 columns')
        ->and($core)->toContain('created_at')
        ->and($core)->toContain('|---:|')
        ->and($core)->toContain('Never an empty table')
        ->and($core)->toContain('bulleted list');
});

it('the core has the few-shot cases: list (good and bad), one item, no result, preview with the Conferma button', function () {
    $core = AiAgentInstructions::core();

    expect($core)->toContain('Ecco i clienti trovati:')
        ->and($core)->toContain('|---|--|')
        ->and($core)->toContain('| Cliente | Città |')
        ->and($core)->toContain('Non ho trovato')
        ->and($core)->toContain('«Conferma»')
        ->and($core)->toContain('«sì»');
});

it('the core keeps every section and stays byte-identical, with nothing that varies per request', function () {
    $core = AiAgentInstructions::core();

    foreach (['# Role', '# Constraints', '# Using the tools', '# Format', '# Examples', '# Results to avoid', '# Criteria of success'] as $section) {
        expect($core)->toContain($section);
    }

    expect(AiAgentInstructions::core())->toBe($core)
        ->and(AiAgentInstructions::system())->toBe($core)
        ->and(AiAgentInstructions::system('  '))->toBe($core)
        ->and($core)->not->toMatch('/\b20\d\d-\d\d-\d\d\b/')
        ->and(mb_strlen($core))->toBeLessThan(8000);
});

it('the default additional instructions keep the seven parts and agree with the core on tables', function () {
    $text = AiDefaultInstructions::TEXT;

    foreach (['## Ruolo', '## Vincoli', '## Contesto', '## Formato', '## Esempi', '## Risultati da evitare', '## Criteri di riuscita'] as $part) {
        expect($text)->toContain($part);
    }

    expect(substr_count($text, "\n## "))->toBe(6)
        ->and($text)->not->toContain('Per più di tre voci')
        ->and($text)->toContain('almeno 3');
});
