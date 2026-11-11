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
        ->assertScript("document.querySelector('[data-ui-bound=state-value]').textContent.trim() === 'California'")
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=California]').hasAttribute('data-active')");
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->click('Set New York')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]').value === 'New York'")
        ->assertScript("document.querySelector('[data-ui-bound=state]').textContent.trim() === 'New York'")
        ->assertScript("document.querySelector('[data-ui-bound=state-value]').textContent.trim() === 'New York'");
});

it('tracks a selected value separately from the visible label', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'new')
        ->wait(0.8)
        ->keys($trigger, ['ArrowDown', 'Enter'])
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-bound=remote-state-value]').textContent.trim() === 'NEWJERSEY'")
        ->type($trigger, 'x')
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-bound=remote-state-value]').textContent.trim() === ''")
        ->click('[data-ui-demo="autocomplete-remote"] button[aria-label="Kosongkan isian"]')
        ->wait(0.5)
        ->assertScript("document.querySelector('[data-ui-bound=remote-state]').textContent.trim() === ''")
        ->assertScript("document.querySelector('[data-ui-bound=remote-state-value]').textContent.trim() === ''");
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
        ->assertScript("document.querySelector('[data-ui-bound=remote-state-value]').textContent.trim() === 'NEWJERSEY'")
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

    $page->assertScript(<<<'JS'
        (() => {
            window.__pending = {};
            window.fetch = (url) => new Promise((resolve) => {
                window.__pending[new URL(url, location.origin).searchParams.get('q')] = (labels) => resolve({
                    ok: true,
                    json: async () => labels,
                });
            });

            const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
            input.focus();
            input.value = 'older';
            input.dispatchEvent(new Event('input', { bubbles: true }));

            return true;
        })()
        JS)
        ->wait(0.4)
        ->assertScript("typeof window.__pending.older === 'function'")
        ->assertScript(<<<'JS'
            (() => {
                const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
                input.value = 'newer';
                input.dispatchEvent(new Event('input', { bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("typeof window.__pending.newer === 'function'")
        ->assertScript(<<<'JS'
            (() => {
                window.__pending.newer(['Newer result']);
                window.__pending.older(['Older result']);

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])')).map((option) => option.dataset.label).join('|') === 'Newer result'");
});

it('drops a remote response that completes after clearing', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            window.__pending = {};
            window.fetch = (url) => new Promise((resolve) => {
                window.__pending[new URL(url, location.origin).searchParams.get('q')] = (labels) => resolve({
                    ok: true,
                    json: async () => labels,
                });
            });

            const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
            input.focus();
            input.value = 'new';
            input.dispatchEvent(new Event('input', { bubbles: true }));

            return true;
        })()
        JS)
        ->wait(0.4)
        ->assertScript("typeof window.__pending.new === 'function'")
        ->click('[data-ui-demo="autocomplete-remote"] button[aria-label="Kosongkan isian"]')
        ->wait(0.2)
        ->assertScript(<<<'JS'
            (() => {
                window.__pending.new(['Stale result']);

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]').value === ''")
        ->assertScript("document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])').length === 0");
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
        ->assertScript("document.querySelector('[data-ui-bound=state]').textContent.trim() === ''")
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=autocomplete-local] button[aria-label=\"Kosongkan isian\"]')).display === 'none'")
        ->assertNoJavaScriptErrors();

    assertNoBackendErrors();
});

it('does not expose the clear action when disabled', function () {
    visit('/ui/autocomplete')
        ->on()
        ->desktop()
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-disabled] [role=combobox]').disabled === true")
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=autocomplete-disabled] button[aria-label=\"Kosongkan isian\"]')).display === 'none'");
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

    $page->assertScript(<<<'JS'
        (() => {
            document.body.style.paddingTop = '900px';
            const trigger = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');

            window.scrollTo(0, trigger.getBoundingClientRect().top + window.scrollY - (window.innerHeight - 120));

            return true;
        })()
        JS)
        ->click($trigger)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');
                const popover = trigger.closest('[data-ui-autocomplete]').querySelector('[data-ui-popover]');
                const triggerRect = trigger.getBoundingClientRect();
                const popoverRect = popover.getBoundingClientRect();

                return popover.matches(':popover-open') && popoverRect.bottom <= triggerRect.top + 1;
            })()
            JS)
        ->type($trigger, 'cal')
        ->wait(0.5)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');
                const popover = trigger.closest('[data-ui-autocomplete]').querySelector('[data-ui-popover]');
                const triggerRect = trigger.getBoundingClientRect();
                const popoverRect = popover.getBoundingClientRect();

                return Math.abs(triggerRect.top - popoverRect.bottom) <= 6 && popoverRect.height < 100;
            })()
            JS);
});

it('skips disabled local items during keyboard navigation', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->click($trigger)
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=Alaska]').hasAttribute('data-active')")
        ->assertScript("!document.querySelector('[data-ui-demo=autocomplete-local] [data-ui-option][data-value=Alabama]').hasAttribute('data-active')");
});

it('discards results when the query changes before the debounce fires', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');

            input.focus();
            input.value = 'new';
            input.dispatchEvent(new Event('input', { bubbles: true }));

            setTimeout(() => {
                input.value = 'zzz';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }, 50);

            return true;
        })()
        JS)
        ->wait(0.8)
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [data-ui-option]:not([hidden])')).length === 0")
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=autocomplete-remote] [role=status]')).some((element) => element.textContent.trim() === 'Tidak ada hasil' && getComputedStyle(element).display !== 'none')");
});

it('flips above when remote results expand near the bottom', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->assertScript(<<<'JS'
        (() => {
            document.body.style.paddingTop = '900px';
            const trigger = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');

            window.scrollTo(0, trigger.getBoundingClientRect().top + window.scrollY - (window.innerHeight - 120));

            return true;
        })()
        JS)
        ->click($trigger)
        ->wait(0.2)
        ->type($trigger, 'new')
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');
                const popover = trigger.closest('[data-ui-autocomplete]').querySelector('[data-ui-popover]');
                const triggerRect = trigger.getBoundingClientRect();
                const popoverRect = popover.getBoundingClientRect();

                return popover.matches(':popover-open') && popoverRect.bottom <= triggerRect.top + 1;
            })()
            JS);
});

it('dispatches change and clear events', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-local"] [role="combobox"]';

    $page->assertScript(<<<'JS'
        (() => {
            window.__uiEvents = [];
            const element = document.querySelector('[data-ui-demo=autocomplete-local] [role=combobox]');

            ['open', 'close', 'change', 'clear'].forEach((name) => element.addEventListener(name, () => window.__uiEvents.push(name)));

            return true;
        })()
        JS)
        ->click($trigger)
        ->type($trigger, 'cal')
        ->wait(0.3)
        ->click('[data-ui-demo="autocomplete-local"] button[aria-label="Kosongkan isian"]')
        ->wait(0.4)
        ->assertScript("['open', 'close', 'change', 'clear'].every((name) => window.__uiEvents.includes(name))");
});

it('does not commit a stale suggestion while a new remote query is loading', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->click($trigger)
        ->type($trigger, 'new')
        ->wait(0.8)
        ->keys($trigger, ['ArrowDown', 'Enter'])
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-bound=remote-state]').textContent.trim() === 'New Jersey'")
        ->assertScript(<<<'JS'
            (() => {
                const input = document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]');

                input.focus();
                input.value = 'zzz';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-remote] [role=combobox]').value === 'zzz'")
        ->assertScript("document.querySelector('[data-ui-bound=remote-state]').textContent.trim() === 'zzz'");
});

it('waits for a remote query before opening', function () {
    $page = visit('/ui/autocomplete')->on()->desktop();
    $trigger = '[data-ui-demo="autocomplete-remote"] [role="combobox"]';

    $page->click($trigger)
        ->wait(0.2)
        ->assertScript("!document.querySelector('[data-ui-demo=autocomplete-remote] [data-ui-popover]').matches(':popover-open')")
        ->type($trigger, 'n')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=autocomplete-remote] [data-ui-popover]').matches(':popover-open')");
});
