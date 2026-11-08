<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for the select listbox and pillbox demos.
 */
#[Layout('ui.layout')]
class Select extends Component
{
    public string $industry = 'web';

    /**
     * @var array<int, string>
     */
    public array $industries = ['web'];

    /**
     * @var array<int, string>
     */
    public array $tags = ['design'];

    /**
     * Swap the single selection for the "legal" demo option.
     */
    public function resetIndustry(): void
    {
        $this->industry = 'legal';
    }

    public function render(): View
    {
        return view('ui.select');
    }
}
