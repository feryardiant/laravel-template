<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\PersonRelativeType;
use App\Enums\Role;
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
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('owner_organization_id')->nullable();

            $table->string('name');
            $table->enum('gender', Gender::cases())->nullable();
            $table->string('photo_url')->nullable();
            $table->string('summary')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_organization_id');
        });

        Schema::create('people_relatives', function (Blueprint $table) {
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('relative_id')->constrained('people')->cascadeOnDelete();

            $table->enum('type', PersonRelativeType::cases())->nullable();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('owner_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('root_organization_id')->nullable()->constrained('organizations')->restrictOnDelete();
            $table->boolean('is_tenant')->default(false);

            $table->string('name');
            $table->string('summary')->nullable();
            $table->string('logo_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_organization_id');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->foreign('owner_organization_id')->references('id')->on('organizations')->nullOnDelete();
        });

        Schema::create('organization_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('stakeholder');

            $table->string('code')->nullable();
            $table->enum('type', OrganizationRelationshipType::cases())->nullable();
            $table->enum('kind', SubsidiaryKind::cases())->nullable();
            $table->enum('role', Role::cases())->nullable();
            $table->boolean('is_primary')->nullable();
            $table->string('remark')->nullable();
            $table->date('start_date')->nullable();
            $table->date('finish_date')->nullable();

            $table->unique(['organization_id', 'code']);
            $table->unique(
                ['stakeholder_type', 'stakeholder_id', 'type', 'is_primary'],
                'org_relationship_primary_unique',
            );
        });

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

        $connection->statement(
            'CREATE UNIQUE INDEX org_relationship_subsidiary_unique ON organization_relationships'
            .' (stakeholder_type, stakeholder_id) WHERE type = \'subsidiary\''
        );

        $connection->statement(
            'CREATE UNIQUE INDEX org_relationship_owner_unique ON organization_relationships'
            .' (organization_id) WHERE type = \'member\' AND role = \'0\''
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_relationships');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('people_relatives');
        Schema::dropIfExists('people');

        $connection = DB::connection();

        if (! \in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            return;
        }

        $connection->statement('DROP INDEX IF EXISTS organizations_root_tenant_unique');
    }
};
