<?php

declare(strict_types=1);

namespace App\Livewire\Ui;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Development page for datetime single and range modes.
 */
#[Layout('ui.layout')]
class DatetimePicker extends Component
{
    public ?string $startAt = '2026-11-08T10:30';

    /**
     * @var array{start: ?string, end: ?string}
     */
    public array $schedule = [
        'start' => '2026-11-08T09:00',
        'end' => '2026-11-10T17:00',
    ];

    public ?string $emptyAt = null;

    public ?string $confirmedAt = '2026-11-08T10:30';

    /**
     * Move the single datetime demo to 10 Nov 2026 14:30.
     */
    public function moveStartAt(): void
    {
        $this->startAt = '2026-11-10T14:30';
    }

    public function render(): View
    {
        return view('ui.datetime-picker');
    }
}
