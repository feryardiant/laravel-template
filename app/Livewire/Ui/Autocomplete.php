<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for local and remote autocomplete demos.
 */
#[Layout('ui.layout')]
class Autocomplete extends Component
{
    public string $state = '';

    public string $stateValue = '';

    public string $remoteState = '';

    public string $remoteStateValue = '';

    /**
     * Set the local demo value to "New York".
     */
    public function setState(): void
    {
        $this->state = 'New York';
    }

    public function render(): View
    {
        return view('ui.autocomplete');
    }
}
