<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\OrganizationRelationshipType;
use App\Enums\PersonRelativeType;
use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

            $table->string('name');
            $table->enum('gender', Gender::cases())->nullable();
            $table->string('summary')->nullable();

            $table->timestamps();
            $table->softDeletes();
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
            $table->foreignId('root_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->boolean('is_tenant')->default(false);

            $table->string('name');
            $table->string('summary')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('stakeholder');

            $table->string('code')->nullable();
            $table->enum('type', OrganizationRelationshipType::cases())->nullable();
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
    }
};
