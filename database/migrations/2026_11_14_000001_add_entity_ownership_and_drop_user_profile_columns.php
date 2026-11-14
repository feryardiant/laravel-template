<?php

declare(strict_types=1);

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
        Schema::table('people', function (Blueprint $table): void {
            $table->foreignId('owner_organization_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index('owner_organization_id');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->foreignId('owner_organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->index('owner_organization_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['name', 'avatar_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->string('avatar_url')->nullable();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropForeign(['owner_organization_id']);
            $table->dropIndex(['owner_organization_id']);
            $table->dropColumn('owner_organization_id');
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->dropForeign(['owner_organization_id']);
            $table->dropIndex(['owner_organization_id']);
            $table->dropColumn('owner_organization_id');
        });
    }
};
