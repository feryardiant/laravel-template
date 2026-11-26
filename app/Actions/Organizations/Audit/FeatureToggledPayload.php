<?php

declare(strict_types=1);

namespace App\Actions\Organizations\Audit;

use App\Actions\AuditLogs\AuditPayload;
use App\Enums\Feature;

/**
 * Audit payload describing a toggled organization feature.
 */
final class FeatureToggledPayload extends AuditPayload
{
    public function __construct(public string $feature, public bool $enabled) {}

    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'enabled' => $this->enabled,
        ];
    }

    /**
     * Rebuild the payload from its stored representation.
     */
    public static function fromArray(array $data): static
    {
        return new self((string) $data['feature'], (bool) $data['enabled']);
    }

    /**
     * Describe the feature and whether it is now on or off.
     */
    public function details(): array
    {
        return [__(':feature: :state', [
            'feature' => self::featureLabel($this->feature),
            'state' => $this->enabled ? __('On') : __('Off'),
        ])];
    }

    /**
     * Map a feature key to its translated label.
     */
    private static function featureLabel(string $feature): string
    {
        return Feature::tryFrom($feature)?->label() ?? $feature;
    }
}
