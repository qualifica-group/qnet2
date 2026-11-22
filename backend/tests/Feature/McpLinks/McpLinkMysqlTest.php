<?php

use App\Enums\McpLinkReason;
use App\Models\McpLink;
use App\Services\McpLinks\McpLinkService;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Proofs that need real locks and isolation: they run only against MySQL (see the
| connection environment variables in the test command) and commit their data, so
| a second connection of the same process can see it. Without MySQL no test of this
| file is registered.
*/

if (getenv('DB_CONNECTION') === 'mysql') {
    $truncate = function (): void {
        Schema::disableForeignKeyConstraints();
        foreach (['mcp_link_tokens', 'mcp_links', 'personal_access_tokens', 'api_clients', 'users'] as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
    };

    beforeEach(function () use ($truncate) {
        config(['mcp-link.release_commit' => 'abc1234']);
        config(['database.connections.mysql_b' => config('database.connections.mysql')]);

        // The data is committed (a second connection must see it): start from empty tables.
        if (! Schema::hasTable('mcp_links')) {
            Artisan::call('migrate:fresh');
        }

        $truncate();
    });

    afterEach(function () use ($truncate) {
        DB::purge('mysql_b');
        $truncate();
    });

    it('ends a drop that waits for a token a logout holds in a lock wait, never in a deadlock', function () {
        [$key, $credential, $user, $link] = openLinkFor();
        callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();
        $other = DB::connection('mysql_b');

        // A logout in flight: the token row is deleted and, by cascade, its bridge row. Not committed yet.
        $other->beginTransaction();
        $other->table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            app(McpLinkService::class)->dropForUser($user, McpLinkReason::PasswordChanged);
            $error = null;
        } catch (Throwable $e) {
            $error = $e;
        }

        $other->commit();

        expect($error)->toBeInstanceOf(DeadlockException::class)
            ->and($error->getMessage())->toContain('Lock wait timeout')
            ->and($error->getMessage())->not->toContain('Deadlock found');

        // Once the logout has ended, the same drop completes.
        app(McpLinkService::class)->dropForUser($user, McpLinkReason::PasswordChanged);

        expect($link->fresh()->revoked_at)->not->toBeNull()
            ->and($user->tokens()->count())->toBe(0);
    });

    it('sees, inside a transaction that already read, a token and a link committed meanwhile (REPEATABLE READ)', function () {
        [$key, $credential, $user, $link] = openLinkFor();
        callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();
        $other = DB::connection('mysql_b');

        DB::beginTransaction();
        DB::table('mcp_links')->count();
        DB::table('personal_access_tokens')->count();

        // Committed by another connection after the snapshot of this transaction.
        $newLinkId = $other->table('mcp_links')->insertGetId([
            'user_id' => $user->id,
            'api_client_id' => $link->api_client_id,
            'mcp_client' => 'late',
            'credential_hash' => McpLink::fingerprint('late'),
            'password_fingerprint' => $link->password_fingerprint,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $lateTokenId = $other->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->id,
            'name' => McpLink::tokenName($link->id),
            'token' => hash('sha256', 'late-token'),
            'abilities' => json_encode(McpLink::tokenAbilities($link->id)),
            'api_client_id' => $link->api_client_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(McpLinkService::class)->dropForUser($user, McpLinkReason::PasswordChanged);
        DB::commit();

        expect(McpLink::query()->whereKey([$link->id, $newLinkId])->whereNull('revoked_at')->count())->toBe(0)
            ->and(DB::table('personal_access_tokens')->where('id', $lateTokenId)->exists())->toBeFalse()
            ->and($user->tokens()->count())->toBe(0);
    });

    it('takes the same lock order from every entry, with no lock on the bridge table', function () {
        [$key, $credential, , $link] = openLinkFor();
        callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)->assertOk();

        $locking = collect($statements)->filter(fn (string $sql) => str_contains($sql, 'for update'))->values();

        expect($locking->first())->toContain('`users`')
            ->and($locking->contains(fn (string $sql) => str_contains($sql, 'mcp_link_tokens')))->toBeFalse();
    });
}
