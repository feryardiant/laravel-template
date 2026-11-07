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
        ->assertScript("document.querySelector('[data-ui-bound=state]').textContent.trim() === 'California'")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=California]').hasAttribute('data-active')");
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
        ->assertScript("document.querySelector('[data-ui-bound=remote-state]').textContent.trim() === 'New Jersey'")
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]')).find((option) => option.dataset.label === 'New Jersey')?.hasAttribute('data-active') === true");
});

it('shows the empty state for remote searches', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'zzz')
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=autocomplete-remote] [role=listbox]')).display === 'none'")
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

it('closes on a mouse selection and on an outside click', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'cal')
        ->wait(0.3)
        ->click('[data-ui-demo="autocomplete-local"] [data-ui-option][data-value="California"]')
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]').getAttribute('aria-expanded') === 'false'")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=California]').hasAttribute('data-active')")
        ->click($trigger)
        ->wait(0.2)
        ->click('[data-ui-outside]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]').getAttribute('aria-expanded') === 'false'");
});

it('opens above the trigger and stays anchored while filtering', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->assertScript("(() => {
        document.body.style.paddingTop = '900px';
        const trigger = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');

        window.scrollTo(0, trigger.getBoundingClientRect().top + window.scrollY - (window.innerHeight - 120));

        return true;
    })()")
        ->click($trigger)
        ->wait(0.3)
        ->assertScript("(() => {
            const trigger = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');
            const popover = document.getElementById(trigger.getAttribute('aria-controls'));
            const triggerRect = trigger.getBoundingClientRect();
            const popoverRect = popover.getBoundingClientRect();

            return popover.matches(':popover-open') && popoverRect.bottom <= triggerRect.top + 1;
        })()")
        ->type($trigger, 'cal')
        ->wait(0.5)
        ->assertScript("(() => {
            const trigger = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');
            const popover = document.getElementById(trigger.getAttribute('aria-controls'));
            const triggerRect = trigger.getBoundingClientRect();
            const popoverRect = popover.getBoundingClientRect();

            return Math.abs(triggerRect.top - popoverRect.bottom) <= 6 && popoverRect.height < 100;
        })()");
});

it('skips disabled local items during keyboard navigation', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->click($trigger)
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=Alaska]').hasAttribute('data-active')")
        ->assertScript("!document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=Alabama]').hasAttribute('data-active')");
});

it('uses the placeholder as an accessible name when unlabeled', function () {
    visit('/ui/autocomplete')
        ->on()
        ->desktop()
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-unlabeled] [role=combobox]').getAttribute('aria-label') === 'Choose a state'");
});

it('discards results when the query changes before the debounce fires', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->assertScript("(() => {
        const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');

        input.focus();
        input.value = 'new';
        input.dispatchEvent(new Event('input', { bubbles: true }));

        setTimeout(() => {
            input.value = 'zzz';
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }, 50);

        return true;
    })()")
        ->wait(0.8)
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])')).length === 0")
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [role=status]')).some((element) => element.textContent.trim() === 'Tidak ada hasil' && getComputedStyle(element).display !== 'none')");
});

it('flips above when remote results expand near the bottom', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->assertScript("(() => {
        document.body.style.paddingTop = '900px';
        const trigger = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');

        window.scrollTo(0, trigger.getBoundingClientRect().top + window.scrollY - (window.innerHeight - 120));

        return true;
    })()")
        ->click($trigger)
        ->wait(0.2)
        ->type($trigger, 'new')
        ->wait(0.8)
        ->assertScript("(() => {
            const trigger = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
            const popover = document.getElementById(trigger.getAttribute('aria-controls'));
            const triggerRect = trigger.getBoundingClientRect();
            const popoverRect = popover.getBoundingClientRect();

            return popover.matches(':popover-open') && popoverRect.bottom <= triggerRect.top + 1;
        })()");
});

it('dispatches change and clear events', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->assertScript("(() => {
        window.__uiEvents = [];
        const element = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');

        ['open', 'close', 'change', 'clear'].forEach((name) => element.addEventListener(name, () => window.__uiEvents.push(name)));

        return true;
    })()")
        ->click($trigger)
        ->type($trigger, 'cal')
        ->wait(0.3)
        ->click('[data-ui-demo="autocomplete-local"] button[aria-label="Kosongkan isian"]')
        ->wait(0.4)
        ->assertScript("['open', 'close', 'change', 'clear'].every((name) => window.__uiEvents.includes(name))");
});

it('renders disabled and invalid states', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->assertScript("document.querySelector('[data-ui-demo=autocomplete-disabled] [role=combobox]').disabled === true")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-invalid] [role=combobox]').getAttribute('aria-invalid') === 'true'");
});
