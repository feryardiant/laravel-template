<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for single, multiple and 12-hour time picking.
 */
#[Layout('ui.layout')]
class TimePicker extends Component
{
    public ?string $time = '09:00';

    public ?string $time12 = '13:30';

    /**
     * @var array<int, string>
     */
    public array $times = ['09:00', '11:30'];

    /**
     * Move the single-time demo to 15:30.
     */
    public function moveTime(): void
    {
        $this->time = '15:30';
    }

    public function render(): View
    {
        return view('ui.time-picker');
    }
}
