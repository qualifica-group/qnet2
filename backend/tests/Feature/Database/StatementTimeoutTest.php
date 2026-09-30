<?php

use App\Http\Middleware\LimitStatementDuration;
use App\Support\Database\StatementTimeout;
use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function timeout_query_exception(int $driver_code): QueryException
{
    $previous = new PDOException('secret SQL detail');
    $previous->errorInfo = ['70100', $driver_code, 'Query execution was interrupted'];

    return new QueryException('mysql', 'select * from secret_table', [], $previous);
}

function mysql_connection_stub(bool $is_maria): MySqlConnection
{
    $connection = Mockery::mock(MySqlConnection::class);
    $connection->shouldReceive('isMaria')->andReturn($is_maria);

    return $connection;
}

it('builds the MariaDB statement in seconds', function () {
    expect(StatementTimeout::statementFor(mysql_connection_stub(true), 25))
        ->toBe('SET SESSION max_statement_time = 25');
});

it('builds the MySQL statement in milliseconds', function () {
    expect(StatementTimeout::statementFor(mysql_connection_stub(false), 25))
        ->toBe('SET SESSION max_execution_time = 25000');
});

it('builds nothing when disabled or on a non MySQL connection', function () {
    expect(StatementTimeout::statementFor(mysql_connection_stub(true), 0))->toBeNull()
        ->and(StatementTimeout::statementFor(Mockery::mock(Connection::class), 25))->toBeNull();
});

it('is a no-op on sqlite', function () {
    config()->set('database.statement_timeout.seconds', 25);

    $response = (new LimitStatementDuration)->handle(
        Request::create('/api/anything'),
        fn () => response()->json(['ok' => true]),
    );

    expect($response->getStatusCode())->toBe(200);
})->skip(fn () => DB::connection()->getDriverName() !== 'sqlite');

it('sets the session limit for regular api paths only', function (string $path, bool $expected) {
    $connection = mysql_connection_stub(true);
    DB::shouldReceive('connection')->andReturn($connection);
    $expected
        ? DB::shouldReceive('statement')->once()->with('SET SESSION max_statement_time = 25')
        : DB::shouldReceive('statement')->never();
    config()->set('database.statement_timeout.seconds', 25);

    (new LimitStatementDuration)->handle(Request::create($path), fn () => response()->json([]));
})->with([
    'table rows' => ['/api/tables/leads/rows', true],
    'report dashboard' => ['/api/quotes/report/dashboard', true],
    'import' => ['/api/imports/leads/5/confirm', false],
    'export' => ['/api/exports/leads', false],
    'export download' => ['/api/exports/leads/3/download', false],
    'mass migration' => ['/api/migrations/mass-runs', false],
    'migration import' => ['/api/migrations/users/import', false],
    'request report create' => ['/api/quotes/report', false],
    'request report download' => ['/api/quotes/report/9/download', false],
    'time entries export' => ['/api/time-entries/exports/monthly', false],
]);

it('skips everything when the limit is 0', function () {
    DB::shouldReceive('statement')->never();
    config()->set('database.statement_timeout.seconds', 0);

    (new LimitStatementDuration)->handle(Request::create('/api/x'), fn () => response()->json([]));
});

it('carries the middleware on api routes', function () {
    $route = Route::getRoutes()->match(Request::create('/api/auth/login', 'POST'));

    expect(app('router')->gatherRouteMiddleware($route))->toContain(LimitStatementDuration::class);
});

it('maps a statement timeout to the envelope without leaking internals', function (int $code) {
    Route::get('/api/_timeout_probe', fn () => throw timeout_query_exception($code))->middleware('api');

    $response = $this->getJson('/api/_timeout_probe');

    $response->assertStatus(503)->assertExactJson([
        'success' => false,
        'message' => 'The operation took too long: narrow your search or filters and try again.',
    ]);
    expect($response->getContent())->not->toContain('secret');
})->with([1969, 3024]);

it('answers the timeout message in Italian', function () {
    Route::get('/api/_timeout_probe', fn () => throw timeout_query_exception(1969))->middleware('api');

    $this->getJson('/api/_timeout_probe', ['Accept-Language' => 'it'])
        ->assertStatus(503)
        ->assertJsonPath('message', "L'operazione ha richiesto troppo tempo: restringi la ricerca o i filtri e riprova.");
});

it('does not map other query errors', function () {
    Route::get('/api/_other_probe', fn () => throw timeout_query_exception(1213));

    $this->getJson('/api/_other_probe')->assertStatus(500);
});
