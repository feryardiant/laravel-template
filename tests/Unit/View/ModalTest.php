<?php

declare(strict_types=1);

use Tests\TestCase;

it('moves the submit handler from the modal to its form', function () {
    /** @var TestCase $this */
    $view = $this->blade(
        '<x-modal mode="form" wire:submit="save" title="Edit"><p>Body</p><x-slot:actions><button type="submit">Save</button></x-slot:actions></x-modal>',
    );

    expect(substr_count((string) $view, 'wire:submit'))->toBe(1);

    $view->assertSee('wire:submit="save"', false)
        ->assertSee('Body')
        ->assertSee('Save')
        ->assertSee(__('Cancel'));
});

it('renders confirm mode with a cancel label and actions', function () {
    /** @var TestCase $this */
    $view = $this->blade(
        '<x-modal mode="confirm" cancel="Dismiss" title="Confirm"><p>Body</p><x-slot:actions><button>Delete</button></x-slot:actions></x-modal>',
    );

    $view->assertSee('Dismiss')
        ->assertSee('Delete')
        ->assertSee('Body')
        ->assertDontSee('<form', false);
});

it('renders default mode without a footer', function () {
    /** @var TestCase $this */
    $view = $this->blade('<x-modal title="Info"><p>Body</p></x-modal>');

    $view->assertSee('Body')
        ->assertDontSee('wire:submit', false)
        ->assertDontSee(__('Cancel'));
});
