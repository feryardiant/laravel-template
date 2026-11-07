<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

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

    public function resetIndustry(): void
    {
        $this->industry = 'legal';
    }

    public function render(): View
    {
        return view('ui.select');
    }
}
