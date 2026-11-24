<?php

declare(strict_types=1);

namespace App\Casts;

use App\Actions\AuditLogs\AuditPayload;
use App\Models\AuditLog;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Store an audit payload together with its class so it can be rebuilt typed.
 *
 * @implements CastsAttributes<AuditPayload|null, mixed>
 */
final class AsAuditPayload implements CastsAttributes
{
    /**
     * Rebuild the typed payload from its stored class name and array form.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?AuditPayload
    {
        if ($value === null) {
            return null;
        }

        if (! $model instanceof AuditLog) {
            throw new UnexpectedValueException('The audit payload cast only supports audit logs.');
        }

        $stored = \is_string($value) ? json_decode($value, true) : $value;

        // Unknown or not-yet-migrated rows keep their raw data instead of
        // breaking the audit page.
        if (
            ! \is_array($stored)
            || ! array_is_list($stored)
            || \count($stored) !== 2
            || ! \is_string($stored[0])
            || ! \is_array($stored[1])
            || ! is_a($stored[0], AuditPayload::class, true)
        ) {
            return null;
        }

        /** @var class-string<AuditPayload> $class */
        $class = $stored[0];

        return $class::fromEvent($model->event, $model->subject, $stored[1]);
    }

    /**
     * Store the payload's class name next to its array form.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof AuditPayload) {
            throw new InvalidArgumentException('An audit log change must be an instance of AuditPayload.');
        }

        return json_encode([$value::class, $value->toArray()], JSON_THROW_ON_ERROR);
    }
}
