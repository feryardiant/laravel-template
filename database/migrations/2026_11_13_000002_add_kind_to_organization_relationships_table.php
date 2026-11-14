<?php

declare(strict_types=1);

use App\Actions\Organizations\ReconcileOwners;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
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
        $supportsPartialIndexes = \in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true);

        // Earlier releases let several members hold Owner; keep a usable one
        // so the unique index below can be created on an existing database.
        app(ReconcileOwners::class)->handle();

        // shortcut: only pgsql/sqlite support partial indexes, add an
        // application-level check if another driver is ever supported.
        if ($supportsPartialIndexes) {
            $connection->statement(
                'CREATE UNIQUE INDEX org_relationship_subsidiary_unique ON organization_relationships'
                .' (stakeholder_type, stakeholder_id) WHERE type = \'subsidiary\''
            );

            $connection->statement(
                'CREATE UNIQUE INDEX org_relationship_owner_unique ON organization_relationships'
                .' (organization_id) WHERE type = \'member\' AND role = \'0\''
            );
        }

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ApprovalRequestType::cases());
            $table->foreignId('requester_person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('approver_person_id')->constrained('people')->cascadeOnDelete();
            $table->enum('status', ApprovalRequestStatus::cases())
                ->default(ApprovalRequestStatus::Pending->value);
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['approver_person_id', 'status']);
        });

        if ($supportsPartialIndexes) {
            $connection->statement(
                'CREATE UNIQUE INDEX approval_request_pending_unique ON approval_requests'
                .' (organization_id) WHERE status = \'pending\''
            );
        }

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

        Schema::dropIfExists('approval_requests');

        if (\in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $connection->statement('DROP INDEX IF EXISTS org_relationship_owner_unique');
            $connection->statement('DROP INDEX IF EXISTS org_relationship_subsidiary_unique');
        }

        Schema::table('organization_relationships', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
