<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for date picker modes, presets and constraints.
 */
#[Layout('ui.layout')]
class DatePicker extends Component
{
    public ?string $date = null;

    public ?string $confirmed = null;

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $range = ['start' => null, 'end' => null];

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->confirmed = now()->format('Y-m-d');
        $this->range = [
            'start' => now()->startOfMonth()->format('Y-m-d'),
            'end' => now()->startOfMonth()->addDays(4)->format('Y-m-d'),
        ];
    }

    /**
     * Move the single-date demo five days ahead of today.
     */
    public function moveDate(): void
    {
        $this->date = now()->addDays(5)->format('Y-m-d');
    }

    public function render(): View
    {
        return view('ui.date-picker');
    }
}
