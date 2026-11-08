<?php

declare(strict_types=1);

use App\Models\Organization;
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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('addressable');
            $table->string('line');
            $table->char('rt', 3)->nullable();
            $table->char('rw', 3)->nullable();
            $table->char('village_code', 13)->nullable();
            $table->char('district_code', 8)->nullable();
            $table->char('regency_code', 5)->nullable();
            $table->char('province_code', 2)->nullable();
            $table->char('postal_code', 5)->nullable();

            $table->timestamps();
        });

        // Kept from the package stub, but unused by this app.
        // Schema::create('has_one_addresses', function (Blueprint $table) {
        //     $table->id();
        // });

        // Schema::create('has_many_addresses', function (Blueprint $table) {
        //     $table->id();
        // });

        // An organization has exactly one address; a person may have many.
        // shortcut: only pgsql/sqlite support partial indexes, add an
        // application-level check if another driver is ever supported.
        $connection = DB::connection();

        if (\in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $connection->statement(\sprintf(
                "CREATE UNIQUE INDEX addresses_organization_unique ON addresses (addressable_id) WHERE addressable_type = '%s'",
                Organization::class,
            ));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('has_many_addresses');
        Schema::dropIfExists('has_one_addresses');
        Schema::dropIfExists('addresses');
    }
};
