<?php

declare(strict_types=1);

it('has no serious accessibility issues on the autocomplete gallery', function (string $device) {
    visit('/ui/autocomplete')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('filters local items and selects with the keyboard', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'cal')
        ->wait(0.3)
        ->assertScript("document.querySelectorAll('[data-ui-demo=autocomplete-local] [data-ui-option]:not([hidden])').length === 1")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option]:not([hidden])').dataset.label === 'California'")
        ->keys($trigger, 'Enter')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]').value === 'California'")
        ->assertScript("document.querySelector('[data-ui-bound=state]').textContent.trim() === 'California'");
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->click('Set New York')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]').value === 'New York'")
        ->assertScript("document.querySelector('[data-ui-bound=state]').textContent.trim() === 'New York'");
});

it('loads remote suggestions and selects one', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'new')
        ->wait(0.8)
        ->assertScript("document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])').length === 4")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])').dataset.label === 'New Hampshire'")
        ->keys($trigger, ['ArrowDown', 'Enter'])
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]').value === 'New Jersey'")
        ->assertScript("document.querySelector('[data-ui-bound=remote-state]').textContent.trim() === 'New Jersey'");
});

it('shows the empty state for remote searches', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'zzz')
        ->wait(0.8)
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [role=status]')).some((element) => element.textContent.trim() === 'Tidak ada hasil' && getComputedStyle(element).display !== 'none')");
});

it('ignores a slower older remote response', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->assertScript("(() => {
        const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
        input.focus();
        input.value = 'slow';
        input.dispatchEvent(new Event('input', { bubbles: true }));

        return true;
    })()")
        ->wait(0.25)
        ->assertScript("(() => {
            const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
            input.value = 'new';
            input.dispatchEvent(new Event('input', { bubbles: true }));

            return true;
        })()")
        ->wait(1.2)
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])')).map((option) => option.dataset.label).join('|') === 'New Hampshire|New Jersey|New Mexico|New York'");
});

it('clears the value with the clear button', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'cal')
        ->wait(0.3)
        ->click('[data-ui-demo="autocomplete-local"] button[aria-label="Kosongkan isian"]')
        ->wait(0.5)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]').value === ''")
        ->assertScript("document.querySelector('[data-ui-bound=state]').textContent.trim() === ''");
});

it('renders disabled and invalid states', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->assertScript("document.querySelector('[data-ui-demo=autocomplete-disabled] [role=combobox]').disabled === true")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-invalid] [role=combobox]').getAttribute('aria-invalid') === 'true'");
});
