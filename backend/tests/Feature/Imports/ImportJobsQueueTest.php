<?php

use App\Jobs\AnalyzeImportJob;
use App\Jobs\ProcessImportJob;
use App\Jobs\ProcessStagedImportJob;
use App\Jobs\StageImportJob;
use App\Jobs\ValidateImportJob;

dataset('import jobs', [
    AnalyzeImportJob::class,
    StageImportJob::class,
    ProcessStagedImportJob::class,
    ValidateImportJob::class,
    ProcessImportJob::class,
]);

it('is pushed on the dedicated imports queue, never on default', function (string $jobClass) {
    expect(config('imports.queue'))->toBe('imports')
        ->and((new $jobClass(1))->queue)->toBe('imports');

    config(['imports.queue' => 'imports-test']);

    expect((new $jobClass(1))->queue)->toBe('imports-test');
})->with('import jobs');
