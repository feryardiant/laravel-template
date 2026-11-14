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

        $profileColumns = array_values(array_filter(
            ['name', 'avatar_url'],
            fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($profileColumns !== []) {
            Schema::dropColumns('users', $profileColumns);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The dropped user columns are irreversible by design; the guarded
        // up() keeps rollback + migrate safe.
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
