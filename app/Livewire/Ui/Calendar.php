<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Carbon\CarbonImmutable;
use Flux\DateRange;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('ui.layout')]
class Calendar extends Component
{
    public ?string $date = null;

    public ?CarbonImmutable $carbonDate = null;

    public ?DateRange $typedRange = null;

    /**
     * @var array<int, string>
     */
    public array $dates = [];

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $range = ['start' => null, 'end' => null];

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->carbonDate = now();
        $this->typedRange = new DateRange(now()->startOfMonth(), now()->startOfMonth()->addDays(4));
        $this->dates = [];
        $this->range = [
            'start' => now()->startOfMonth()->format('Y-m-d'),
            'end' => now()->startOfMonth()->addDays(4)->format('Y-m-d'),
        ];
    }

    public function moveSingleDate(): void
    {
        $this->date = now()->addDays(5)->format('Y-m-d');
    }

    public function render(): View
    {
        return view('ui.calendar');
    }
}
