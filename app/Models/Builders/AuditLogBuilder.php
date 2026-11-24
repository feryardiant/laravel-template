<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<AuditLog>
 */
final class AuditLogBuilder extends Builder
{
    use Concerns\ForOrganization;
}
