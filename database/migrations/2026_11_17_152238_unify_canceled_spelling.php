<?php

declare(strict_types=1);

use App\Enums\ApprovalRequestStatus;
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
        $this->renameColumn('cancelled_at', 'canceled_at');

        $this->replaceStatus(
            array_map(fn (ApprovalRequestStatus $status): string => $status->value, ApprovalRequestStatus::cases()),
            'cancelled',
            'canceled',
        );

        DB::table('audit_logs')
            ->where('event', 'approval.cancelled')
            ->update(['event' => 'approval.canceled']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceStatus(
            ['pending', 'accepted', 'declined', 'cancelled'],
            'canceled',
            'cancelled',
        );

        DB::table('audit_logs')
            ->where('event', 'approval.canceled')
            ->update(['event' => 'approval.cancelled']);

        $this->renameColumn('canceled_at', 'cancelled_at');
    }

    private function renameColumn(string $from, string $to): void
    {
        if (! Schema::hasColumn('approval_requests', $from)) {
            return;
        }

        Schema::table('approval_requests', function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }

    /**
     * Replace the status check constraint while preserving existing rows.
     *
     * @param  list<string>  $allowed
     */
    private function replaceStatus(array $allowed, string $from, string $to): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE approval_requests DROP CONSTRAINT IF EXISTS approval_requests_status_check');
            DB::table('approval_requests')->where('status', $from)->update(['status' => $to]);
            DB::statement(\sprintf(
                'ALTER TABLE approval_requests ADD CONSTRAINT approval_requests_status_check CHECK (status IN (%s))',
                implode(', ', array_map(fn (string $status): string => "'{$status}'", $allowed)),
            ));

            return;
        }

        if ($driver === 'sqlite') {
            // The old check constraint would reject the new value during the update.
            DB::statement('PRAGMA ignore_check_constraints = ON');
            DB::table('approval_requests')->where('status', $from)->update(['status' => $to]);
            DB::statement('PRAGMA ignore_check_constraints = OFF');

            Schema::table('approval_requests', function (Blueprint $table) use ($allowed): void {
                $table->enum('status', $allowed)
                    ->default(ApprovalRequestStatus::Pending->value)
                    ->change();
            });

            return;
        }

        DB::table('approval_requests')->where('status', $from)->update(['status' => $to]);
    }
};
