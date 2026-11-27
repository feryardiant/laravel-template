<?php

declare(strict_types=1);

use App\Livewire\Dashboard\Index;
use App\Repositories\ContactRepository;
use App\Repositories\MembershipRepository;
use App\Support\DateRange;
use Livewire\Livewire;
use Tests\Stress\TestCase;

describe('Livewire pages', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
        $this->switchTo($this->benchOwner(), $this->bench());
    });

    it('renders the benchmark dashboard', function (): void {
        /** @var TestCase $this */
        $this->measure('bench dashboard', fn () => Livewire::test(Index::class), runs: 3);
    })->group('query-read');

    it('renders the root dashboard', function (): void {
        /** @var TestCase $this */
        $this->switchTo($this->rootOwner(), $this->root());

        $this->measure('root dashboard', fn () => Livewire::test(Index::class), runs: 3);
    })->group('query-read');
});

describe('Query plans', function (): void {
    beforeEach(function (): void {
        /** @var TestCase $this */
        $this->requireQuery();
    });

    it('explains the dashboard range counts', function (): void {
        $range = DateRange::defaultWeek();

        /** @var TestCase $this */
        $sql = $this->capture(fn () => app(ContactRepository::class)->countExpiring($this->bench(), $range));
        $this->explain('dashboard expiring count', $sql[0] ?? '');

        $sql = $this->capture(fn () => app(MembershipRepository::class)->countRecent($this->bench(), $range));
        $this->explain('dashboard recent members', $sql[0] ?? '');
    })->group('query-read');
});
