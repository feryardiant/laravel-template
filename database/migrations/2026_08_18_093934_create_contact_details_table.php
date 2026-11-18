<?php

declare(strict_types=1);

use App\Enums\ContactDetailType;
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
        Schema::create('contact_details', function (Blueprint $table) {
            $table->id();
            $table->morphs('contactable');
            $table->enum('type', ContactDetailType::cases());
            $table->string('value');
            $table->timestamps();

            // The UI stores one record per type for now; drop this index when
            // multiple values are exposed.
            $table->unique(['contactable_type', 'contactable_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_details');
    }
};
