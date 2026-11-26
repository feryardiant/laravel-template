<?php

declare(strict_types=1);

namespace App\Livewire\OrgSettings;

use App\Actions\Tenancies\ToggleFeature;
use App\Enums\Feature;
use App\Livewire\Concerns\HasCurrentOrganization;
use App\Models\Organization;
use Flux\Flux;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Toggles the feature flags available to the current organization.
 */
class Features extends Component
{
    use HasCurrentOrganization;

    /** @var array<string, bool> */
    public array $features = [];

    #[Locked]
    public bool $isRootTenant = false;

    #[Locked]
    public ?int $organizationId = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $organization = $this->currentOrganization();

        abort_if($organization === null, 404);

        $this->authorize('manageFeatures', $organization);

        $this->organizationId = $organization->getKey();
        $this->isRootTenant = $organization->isRootTenant();

        foreach ($this->fields() as $feature) {
            $this->features[$feature->value] = $feature->enabled($organization);
        }
    }

    /**
     * @return list<Feature>
     */
    #[Computed]
    public function fields(): array
    {
        return array_values(array_filter(
            Feature::cases(),
            fn (Feature $feature): bool => ! $feature->isRootOnly() || $this->isRootTenant,
        ));
    }

    public function updatedFeatures(bool $enabled, ?string $key): void
    {
        $feature = $key === null ? null : Feature::tryFrom($key);

        if ($feature === null) {
            return;
        }

        if (! $this->toggle($feature, $enabled)) {
            return;
        }

        Flux::toast(
            variant: 'success',
            text: $enabled
                ? __(':feature enabled.', ['feature' => $feature->label()])
                : __(':feature disabled.', ['feature' => $feature->label()]),
        );
    }

    private function toggle(Feature $feature, bool $enabled): bool
    {
        $organization = $this->requireOrganization([
            404 => static fn (?Organization $organization): bool => $organization === null,
            409 => fn (?Organization $organization): bool => $organization?->getKey() !== $this->organizationId,
        ]);

        $this->authorize('manageFeatures', $organization);

        try {
            return app(ToggleFeature::class)->handle($organization, $feature, $enabled);
        } catch (InvalidArgumentException) {
            // ToggleFeature only throws this for a root-only flag on a child tenant.
            abort(404);
        }
    }
}
