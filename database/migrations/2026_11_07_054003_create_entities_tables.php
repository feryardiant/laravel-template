<?php

declare(strict_types=1);

use App\Enums\Gender;
use App\Enums\OrganizationRelativeType;
use App\Enums\PersonRelativeType;
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
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

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
            $table->foreignId('owner_id')->constrained('people')->cascadeOnDelete();

            $table->string('name');
            $table->string('summary')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organizations_relatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('stakeholder');

            $table->string('code')->unique()->nullable();
            $table->enum('type', OrganizationRelativeType::cases())->nullable();
            $table->boolean('is_primary')->nullable();
            $table->string('remark')->nullable();
            $table->date('start_date')->nullable();
            $table->date('finish_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations_relatives');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('people_relatives');
        Schema::dropIfExists('people');
    }
};
