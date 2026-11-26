<?php

/**
 * Real concurrency: separate PHP processes reserve against one SQLite file at the same instant.
 * SQLite serializes writers on the whole file, so this proves the transaction (counters written first,
 * then summed) lets one reservation through; the row lock itself (`FOR UPDATE`) only exists on MySQL.
 */
const AIC_WORKER = __DIR__.'/../../../Support/ai-spend-worker.php';

/** @param list<string> $args */
function aic_start_worker(string $database, array $args)
{
    $process = proc_open(
        [PHP_BINARY, AIC_WORKER, ...$args],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        array_merge(getenv(), [
            'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database, 'DB_URL' => '', 'DB_FOREIGN_KEYS' => 'false', 'CACHE_STORE' => 'array',
            'XDEBUG_MODE' => 'off',
        ]),
    );

    return [$process, $pipes];
}

/** @return string trimmed stdout of the worker */
function aic_finish_worker(array $worker): string
{
    [$process, $pipes] = $worker;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);
    if ($err !== '') {
        throw new RuntimeException('Worker failed: '.mb_substr($out.$err, 0, 600));
    }

    return trim($out);
}

/**
 * Start one reserving process per message id, release them together, collect their answers.
 *
 * @param  list<int>  $messageIds
 * @return list<string>
 */
function aic_race(string $database, array $messageIds): array
{
    $go = tempnam(sys_get_temp_dir(), 'aic-go');
    unlink($go);
    $workers = array_map(fn (int $id) => aic_start_worker($database, ['reserve', (string) $id, $go]), $messageIds);

    $deadline = microtime(true) + 60;
    while (count(glob($go.'.ready*')) < count($messageIds) && microtime(true) < $deadline) {
        usleep(20_000);
    }
    touch($go);

    $answers = array_map('aic_finish_worker', $workers);
    array_map('unlink', [...glob($go.'.ready*'), $go]);

    return $answers;
}

it('lets exactly one of four simultaneous reservations through near the cap, for a person and for the total', function () {
    $database = tempnam(sys_get_temp_dir(), 'aic-db');
    expect(aic_finish_worker(aic_start_worker($database, ['setup'])))->toBe('done');

    // Same person four times: the person cap (3.5 $) fits one reservation of 3 $
    $samePerson = aic_race($database, [1, 1, 1, 1]);
    expect(aic_finish_worker(aic_start_worker($database, ['reset'])))->toBe('done');
    // Four different people: the person caps are free, the company total (3.5 $) fits one
    $fourPeople = aic_race($database, [1, 2, 3, 4]);

    @unlink($database);
    expect(array_count_values($samePerson))->toEqual(['ok' => 1, 'limit' => 3])
        ->and(array_count_values($fourPeople))->toEqual(['ok' => 1, 'limit' => 3]);
});
