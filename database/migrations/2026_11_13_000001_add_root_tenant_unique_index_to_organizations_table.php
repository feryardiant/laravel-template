<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection();

        // shortcut: only pgsql/sqlite support partial indexes, add an
        // application-level check if another driver is ever supported.
        if (! \in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $connection->statement(
            'CREATE UNIQUE INDEX organizations_root_tenant_unique ON organizations (is_tenant)'
            .' WHERE is_tenant = true AND root_organization_id IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection();

        if (! \in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $connection->statement('DROP INDEX IF EXISTS organizations_root_tenant_unique');
    }
};
