<?php

declare(strict_types=1);

use App\Listeners\RecordAuditLog;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

it('defers audit writes until the database transaction commits', function () {
    expect(is_subclass_of(RecordAuditLog::class, ShouldQueueAfterCommit::class))->toBeTrue();
});
