<?php

declare(strict_types=1);

use App\Enums\OrganizationRelationshipType;
use App\Enums\RelationshipPeriodReason;
use App\Enums\Role;
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
        Schema::create('organization_relationship_periods', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Nulled when the live relationship is detached/deleted so the
            // period survives; the columns below keep it renderable.
            $table->foreignId('organization_relationship_id')
                ->nullable()
                ->constrained('organization_relationships')
                ->nullOnDelete();

            $table->string('stakeholder_type');
            $table->unsignedBigInteger('stakeholder_id');

            $table->enum('type', OrganizationRelationshipType::cases());
            $table->string('code')->nullable();
            $table->enum('role', Role::cases())->nullable();

            $table->date('start_date');
            $table->date('finish_date')->nullable();

            $table->enum('reason', RelationshipPeriodReason::cases())->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index('organization_relationship_id');
            $table->index('actor_id');
            $table->index(['organization_id', 'start_date']);
            $table->index(
                ['organization_id', 'stakeholder_type', 'stakeholder_id', 'start_date'],
                'org_rel_period_entity_index',
            );

        });

        // shortcut: only pgsql/sqlite support partial indexes, add an
        // application-level check if another driver is ever supported.
        if (\in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX org_rel_period_open_unique ON organization_relationship_periods'
                .' (organization_relationship_id)'
                .' WHERE closed_at IS NULL AND organization_relationship_id IS NOT NULL'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_relationship_periods');
    }
};
