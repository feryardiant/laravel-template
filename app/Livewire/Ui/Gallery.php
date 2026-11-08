<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development gallery that links every component page.
 */
#[Layout('ui.layout')]
class Gallery extends Component
{
    public function render(): View
    {
        return view('ui.index');
    }
}
