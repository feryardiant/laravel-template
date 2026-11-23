<?php

declare(strict_types=1);

namespace App\Actions\AuditLogs;

use App\Enums\AuditEvent;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * Typed description of the changes an audited event records.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class AuditPayload implements Arrayable, Stringable
{
    protected ?AuditEvent $event = null;

    protected ?Model $subject = null;

    /**
     * Rebuild the payload from its stored representation.
     *
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * The localized details shown for this change in the audit log.
     *
     * @return list<string>
     */
    abstract public function details(): array;

    /**
     * Rebuild the payload and attach the event and subject it describes.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromEvent(AuditEvent $event, ?Model $subject, array $data): static
    {
        $self = static::fromArray($data);

        $self->event = $event;
        $self->subject = $subject;

        return $self;
    }

    /**
     * Summarize the change for the subject timeline.
     */
    public function __toString(): string
    {
        return $this->event?->label() ?? '';
    }

    /**
     * The subject name, or null when the subject is gone.
     */
    protected function subjectName(): ?string
    {
        $name = $this->subject?->getAttribute('name');

        return \is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * The audited target shown in the Subject column.
     */
    public function subjectLabel(): string
    {
        return $this->subjectName() ?? (string) $this;
    }

    /**
     * The subject title, falling back to the event label when unavailable.
     */
    protected function subjectTitle(): string
    {
        return (string) ($this->subject?->getAttribute('title')
            ?? $this->subjectName()
            ?? $this->event?->label());
    }
}
