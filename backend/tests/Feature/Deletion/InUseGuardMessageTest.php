<?php

use App\Models\Sector;
use App\Models\User;
use App\Services\Deletion\InUseGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function inUseMessage(array $counts): ?string
{
    try {
        app(InUseGuard::class)->assertNoUsage($counts);
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);

        return $exception->getMessage();
    }

    return null;
}

it('0384: pluralizes each use and lists them in order', function () {
    expect(inUseMessage(['invoices' => 3, 'registries' => 1]))
        ->toBe(__('deletion.in_use', ['usages' => '3 invoices, 1 registry']));
});

it('0384: skips the uses with a zero count and does nothing when none is left', function () {
    expect(inUseMessage(['invoices' => 0, 'registries' => 2]))
        ->toBe(__('deletion.in_use', ['usages' => '2 registries']))
        ->and(inUseMessage(['invoices' => 0]))->toBeNull();
});

it('0384: sums the columns that share a label', function () {
    $sector = Sector::factory()->create();

    expect(app(InUseGuard::class)->usageCounts($sector))->toBe([
        'sub_items' => 0,
        'registries' => 0,
        'purchase_orders' => 0,
    ]);
});

it('0384: refuses a table missing from the usage map', function () {
    app(InUseGuard::class)->assertUnused(new User);
})->throws(LogicException::class);
