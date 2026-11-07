<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('ui.layout')]
class Autocomplete extends Component
{
    public string $state = '';

    public string $remoteState = '';

    public function setState(): void
    {
        $this->state = 'New York';
    }

    public function render(): View
    {
        return view('ui.autocomplete');
    }
}
