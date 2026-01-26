<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Helper for DB-backed tests that should not depend on the developer's local MySQL/MariaDB.
 *
 * - Forces sqlite :memory:
 * - Runs migrate:fresh
 */
trait UsesSqliteInMemory
{
    protected function bootSqliteInMemory(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    protected function migrateFreshSqlite(): void
    {
        // In-memory DB is empty on each connection; migrate:fresh makes intent explicit.
        Artisan::call('migrate:fresh', ['--database' => 'sqlite']);
    }
}
