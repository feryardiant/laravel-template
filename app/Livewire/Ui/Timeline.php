<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for the timeline component.
 */
#[Layout('ui.layout')]
class Timeline extends Component
{
    public function render(): View
    {
        return view('ui.timeline');
    }
}
