<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\SubsidiaryKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organization_relationships', function (Blueprint $table) {
            $table->enum('kind', SubsidiaryKind::cases())->nullable();
        });

        DB::table('organization_relationships')
            ->where('type', OrganizationRelationshipType::Subsidiary->value)
            ->update(['kind' => SubsidiaryKind::Subsidiary->value]);

        $connection = DB::connection();

        // shortcut: only pgsql/sqlite support partial indexes, add an
        // application-level check if another driver is ever supported.
        if (! \in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $connection->statement(
            'CREATE UNIQUE INDEX org_relationship_subsidiary_unique ON organization_relationships'
            .' (stakeholder_type, stakeholder_id) WHERE type = \'subsidiary\''
        );

        // Item 3: a root tenant with tenant children must not be hard-deleted.
        // shortcut: sqlite's dropForeign rebuilds the table and would recreate
        // the raw partial root-tenant index without its WHERE clause; support
        // sqlite once the schema builder preserves partial indexes.
        if ($connection->getDriverName() === 'pgsql') {
            Schema::table('organizations', function (Blueprint $table): void {
                $table->dropForeign(['root_organization_id']);
                $table->foreign('root_organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            Schema::table('organizations', function (Blueprint $table): void {
                $table->dropForeign(['root_organization_id']);
                $table->foreign('root_organization_id')
                    ->references('id')
                    ->on('organizations')
                    ->nullOnDelete();
            });
        }

        if (\in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $connection->statement('DROP INDEX IF EXISTS org_relationship_subsidiary_unique');
        }

        Schema::table('organization_relationships', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
