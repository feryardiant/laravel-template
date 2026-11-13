<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::dropColumns('users', ['role']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible by design: the guarded up() keeps rollback + migrate safe.
    }
};
