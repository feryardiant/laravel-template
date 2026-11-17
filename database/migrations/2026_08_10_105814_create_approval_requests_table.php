<?php

declare(strict_types=1);

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ApprovalRequestType::cases());
            $table->foreignId('requester_person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('approver_person_id')->constrained('people')->cascadeOnDelete();
            $table->enum('status', ApprovalRequestStatus::cases())
                ->default(ApprovalRequestStatus::Pending->value);
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['approver_person_id', 'status']);
        });

        $connection = DB::connection();

        if (\in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            $connection->statement(
                'CREATE UNIQUE INDEX approval_request_pending_unique ON approval_requests'
                .' (organization_id) WHERE status = \'pending\''
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
