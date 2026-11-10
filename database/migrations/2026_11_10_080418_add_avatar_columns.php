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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->string('photo_url')->nullable();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropColumns('organizations', ['logo_url']);
        Schema::dropColumns('people', ['photo_url']);
        Schema::dropColumns('users', ['avatar_url']);
    }
};
