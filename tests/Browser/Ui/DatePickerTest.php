<?php

declare(strict_types=1);

it('has no serious accessibility issues on the date picker gallery', function (string $device) {
    visit('/ui/date-picker')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('opens the popover and focuses the active calendar day', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                document.querySelector('[data-ui-demo=date-picker-bounded] [data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-bounded]');
                const trigger = root.querySelector('[data-ui-trigger]');
                const popover = root.querySelector('[data-ui-popover]');

                return popover.matches(':popover-open') &&
                    trigger.getAttribute('aria-expanded') === 'true' &&
                    document.activeElement.closest('td')?.dataset.date === '2026-02-15';
            })()
            JS);
});

it('applies a single selection and closes immediately', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-single]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                const cells = [...root.querySelectorAll('[data-ui-popover] td[data-date]:not([data-outside]):not([aria-disabled=true])')];
                const cell = cells[9];

                cell.querySelector('button').click();
                window.__pickedDate = cell.dataset.date;

                return true;
            })()
            JS)
        ->wait(0.7)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                const trigger = root.querySelector('[data-ui-trigger]');
                const expected = new Intl.DateTimeFormat(document.documentElement.lang, {
                    calendar: 'gregory',
                    day: 'numeric',
                    month: 'short',
                    timeZone: 'UTC',
                    year: 'numeric',
                }).format(new Date(window.__pickedDate + 'T00:00:00Z'));

                return root.querySelector('[data-ui-bound=date]').textContent.trim() === window.__pickedDate &&
                    trigger.value === expected &&
                    trigger.getAttribute('aria-expanded') === 'false' &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                    document.activeElement === trigger;
            })()
            JS);
});

it('requires the footer confirmation in single mode', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-confirm]');
            window.__confirmBound = root.querySelector('[data-ui-bound=confirmed]').textContent.trim();
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-confirm]');
                const cell = root.querySelector('[data-ui-popover] td[data-date]:not([data-outside]):not([aria-disabled=true])');
                cell.querySelector('button').click();
                window.__confirmedDate = cell.dataset.date;

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-confirm]');

                return root.querySelector('[data-ui-popover]').matches(':popover-open') &&
                    root.querySelector('[data-ui-bound=confirmed]').textContent.trim() === window.__confirmBound;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-confirm]');
                const button = [...root.querySelectorAll('[data-ui-popover] button')].find((candidate) => candidate.textContent.trim() === 'Pilih');

                button.click();

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-confirm]');

                return root.querySelector('[data-ui-bound=confirmed]').textContent.trim() === window.__confirmedDate &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
            })()
            JS);
});

it('previews a range and applies it with the footer', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const cells = [...root.querySelectorAll('[data-ui-popover] [role=grid]')][0].querySelectorAll('td[data-date]:not([data-outside])');
                const first = cells[cells.length - 6];
                const second = cells[cells.length - 2];

                first.querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const cells = [...root.querySelectorAll('[data-ui-popover] [role=grid]')][0].querySelectorAll('td[data-date]:not([data-outside])');
                const first = cells[cells.length - 6];
                const second = cells[cells.length - 2];
                const before = root.querySelector('[data-ui-bound=range]').textContent.trim();

                second.querySelector('button').click();
                window.__rangeBefore = before;
                window.__rangeStart = first.dataset.date;
                window.__rangeEnd = second.dataset.date;

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');

                return root.querySelector('[data-ui-bound=range]').textContent.trim() === window.__rangeBefore &&
                    root.querySelectorAll('[data-ui-popover] [data-in-range]').length > 0 &&
                    root.querySelectorAll('[data-ui-popover] [data-start]')[0].dataset.date === window.__rangeStart &&
                    root.querySelectorAll('[data-ui-popover] [data-end]')[0].dataset.date === window.__rangeEnd;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const button = [...root.querySelectorAll('[data-ui-popover] button')].find((candidate) => candidate.textContent.trim() === 'Pilih rentang');

                button.click();

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const bound = root.querySelector('[data-ui-bound=range]').textContent.trim();

                return bound === window.__rangeStart + ' / ' + window.__rangeEnd &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
            })()
            JS);
});

it('discards pending changes on Escape and outside click', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            window.__rangeCommit = root.querySelector('[data-ui-bound=range]').textContent.trim();
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[data-ui-popover] td[data-date]:not([data-outside]):not([aria-disabled=true]) button').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');

                return root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                    root.querySelector('[data-ui-bound=range]').textContent.trim() === window.__rangeCommit;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[data-ui-popover] td[data-date]:not([data-outside]):not([aria-disabled=true]) button').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');

                return root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                    root.querySelector('[data-ui-bound=range]').textContent.trim() === window.__rangeCommit;
            })()
            JS);
});

it('clears the value without opening the popover', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');

                root.querySelector('[aria-label="Kosongkan tanggal"]').click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');

                return root.querySelector('[data-ui-bound=date]').textContent.trim() === '' &&
                    root.querySelector('[data-ui-trigger]').value === '' &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                    getComputedStyle(root.querySelector('[aria-label="Kosongkan tanggal"]')).display === 'none';
            })()
            JS);
});

it('clears a selected single value without JavaScript errors', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-single]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                const cell = [...root.querySelectorAll('[data-ui-popover] td[data-date]:not([data-outside]):not([aria-disabled=true])')][9];

                cell.querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.7)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                root.querySelector('[aria-label="Kosongkan tanggal"]').click();

                return true;
            })()
            JS)
        ->wait(0.4);

    assertNoBackendErrors();

    $page->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');

                return root.querySelector('[data-ui-bound=date]').textContent.trim() === '' &&
                    root.querySelector('[data-ui-trigger]').value === '';
            })()
            JS);
});

it('clears a selected range without JavaScript errors', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const cells = root.querySelectorAll('[data-ui-popover] [role=grid]')[0].querySelectorAll('td[data-date]:not([data-outside])');

                cells[cells.length - 6].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const cells = root.querySelectorAll('[data-ui-popover] [role=grid]')[0].querySelectorAll('td[data-date]:not([data-outside])');

                cells[cells.length - 2].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const confirm = [...root.querySelectorAll('[data-ui-popover] button')].find((button) => button.textContent.trim() === 'Pilih rentang');

                confirm.click();

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[aria-label="Kosongkan tanggal"]').click();

                return true;
            })()
            JS)
        ->wait(0.4);

    assertNoBackendErrors();

    $page->assertNoJavaScriptErrors()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');

                return root.querySelector('[data-ui-trigger]').value === '' &&
                    root.querySelector('[data-ui-bound=range]').textContent.trim() === '/' &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
            })()
            JS);
});

it('applies a preset immediately', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const preset = [...root.querySelectorAll('[data-ui-popover] [role=group] button')].find((candidate) => candidate.textContent.trim() === 'Hari ini');

                preset.click();

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const today = new Date();
                const key = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

                return root.querySelector('[data-ui-bound=range]').textContent.trim() === key + ' / ' + key &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
            })()
            JS);
});

it('disables min, max and unavailable dates', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-bounded]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-bounded]');
                const cell = (key) => root.querySelector('[data-ui-popover] td[data-date="' + key + '"]');

                return cell('2026-02-09').getAttribute('aria-disabled') === 'true' &&
                    cell('2026-02-10').getAttribute('aria-disabled') === null &&
                    cell('2026-02-17').hasAttribute('data-unavailable') &&
                    cell('2026-02-20').getAttribute('aria-disabled') === null &&
                    cell('2026-02-21').getAttribute('aria-disabled') === 'true';
            })()
            JS);
});

it('opens with the keyboard and selects with Enter', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-single]');
            const trigger = root.querySelector('[data-ui-trigger]');

            trigger.focus();
            trigger.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');

                return root.querySelector('[data-ui-popover]').matches(':popover-open') && document.activeElement.closest('td') !== null;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                window.__keyboardDate = document.activeElement.closest('td').dataset.date;
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');

                return root.querySelector('[data-ui-bound=date]').textContent.trim() === window.__keyboardDate &&
                    root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
            })()
            JS);
});

it('traps Tab focus inside the popover', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const popover = root.querySelector('[data-ui-popover]');
                const focusables = [...popover.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]')].filter((element) => element.tabIndex >= 0 && element.offsetWidth > 0);
                const first = focusables[0];
                const last = focusables[focusables.length - 1];

                last.focus();
                popover.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
                const forward = document.activeElement === first;

                first.focus();
                popover.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }));

                return forward && document.activeElement === last;
            })()
            JS);
});

it('writes plain-form hidden fields for single and range', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-plain]');

            return root.querySelector('input[name=plain_date]').value === '2024-02-15' &&
                root.querySelector('input[name="plain_range[start]"]').value === '2024-02-10' &&
                root.querySelector('input[name="plain_range[end]"]').value === '2024-02-14';
        })()
        JS);

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-plain]');
            const picker = root.querySelectorAll('[data-ui-date-picker]')[1];

            picker.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-plain]');
                const picker = root.querySelectorAll('[data-ui-date-picker]')[1];
                const cells = picker.querySelectorAll('[data-ui-popover] [role=grid]')[0].querySelectorAll('td[data-date]:not([data-outside])');
                const first = cells[cells.length - 6];
                const second = cells[cells.length - 2];

                first.querySelector('button').click();
                second.querySelector('button').click();
                window.__plainStart = first.dataset.date;
                window.__plainEnd = second.dataset.date;
                [...picker.querySelectorAll('[data-ui-popover] button')].find((candidate) => candidate.textContent.trim() === 'Pilih rentang').click();

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-plain]');

                return root.querySelector('input[name="plain_range[start]"]').value === window.__plainStart &&
                    root.querySelector('input[name="plain_range[end]"]').value === window.__plainEnd;
            })()
            JS);
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-single]');
            window.__beforeDisplay = root.querySelector('[data-ui-trigger]').value;
            window.__beforeBound = root.querySelector('[data-ui-bound=date]').textContent.trim();

            return true;
        })()
        JS)
        ->click('[data-ui-test=move-date]')
        ->wait(0.7)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                const trigger = root.querySelector('[data-ui-trigger]');

                return trigger.value !== window.__beforeDisplay &&
                    root.querySelector('[data-ui-bound=date]').textContent.trim() !== window.__beforeBound;
            })()
            JS);
});

it('does not open when the picker is disabled', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-disabled]');
                const trigger = root.querySelector('[data-ui-trigger]');

                trigger.click();

                return trigger.disabled && root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
            })()
            JS);
});

it('renders one month by default for range mode', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=date-picker-range] [data-ui-popover]').querySelectorAll('[role=grid]').length === 1");
});

it('keeps touch targets at least 44px on mobile', function () {
    $page = visit('/ui/date-picker')->on()->mobile();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const popover = root.querySelector('[data-ui-popover]');
                const trigger = root.querySelector('[data-ui-trigger]');
                const preset = popover.querySelector('[role=group] button');
                const day = popover.querySelector('td[data-date] button');
                const footer = [...popover.querySelectorAll('button')].find((candidate) => candidate.textContent.trim() === 'Pilih rentang');

                const rect = popover.getBoundingClientRect();

                return trigger.getBoundingClientRect().height >= 44 &&
                    preset.getBoundingClientRect().height >= 44 &&
                    day.getBoundingClientRect().height >= 44 &&
                    footer.getBoundingClientRect().height >= 44 &&
                    Math.round(rect.left) === 8 &&
                    Math.round(rect.width) === window.innerWidth - 16 &&
                    rect.right <= window.innerWidth;
            })()
            JS);
});

it('uses padded text buttons and keeps presets inside their column', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-single]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                const today = [...root.querySelectorAll('[data-ui-calendar] button')].find((button) => button.textContent.trim() === 'Hari ini');

                return today.getBoundingClientRect().width > 32;
            })()
            JS);

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const group = root.querySelector('[data-ui-popover] [role=group]');
                const select = [...root.querySelectorAll('[data-ui-popover] button')].find((button) => button.textContent.trim() === 'Pilih rentang');

                return group.scrollWidth <= group.clientWidth && select.getBoundingClientRect().width > 32;
            })()
            JS);
});

it('only offers single-day presets in single mode', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single-presets]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single-presets]');
                const labels = [...root.querySelectorAll('[data-ui-popover] [role=group] button')].map((button) => button.textContent.trim());

                return labels.length === 2 && labels[0] === 'Hari ini' && labels[1] === 'Kemarin';
            })()
            JS);
});

it('omits presets that violate range constraints', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-preset-constraints]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-preset-constraints]');
                const labels = [...root.querySelectorAll('[data-ui-popover] [role=group] button')].map((button) => button.textContent.trim());

                return labels.length === 2 && labels.includes('Hari ini') && labels.includes('Kemarin');
            })()
            JS);
});

it('omits a preset that contains an unavailable date', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-preset-unavailable]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-preset-unavailable]');
                const labels = [...root.querySelectorAll('[data-ui-popover] [role=group] button')].map((button) => button.textContent.trim());

                return labels.length === 1 && labels[0] === 'Hari ini';
            })()
            JS);
});

it('hides the preset sidebar when every preset is filtered out', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    foreach (['date-picker-preset-empty', 'date-picker-preset-alltime'] as $demo) {
        $page->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo={$demo}]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()")
            ->wait(0.3)
            ->assertScript("(() => {
                const root = document.querySelector('[data-ui-demo={$demo}]');
                const group = root.querySelector('[data-ui-popover] [role=group]');

                return group !== null && group.offsetParent === null && group.querySelectorAll('button').length === 0;
            })()");

        $page->assertScript(<<<'JS'
            (() => {
                document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

                return true;
            })()
            JS)->wait(0.3);
    }
});

it('exposes the active preset through aria-pressed', function () {
    $page = visit('/ui/date-picker')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=date-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                [...root.querySelectorAll('[data-ui-popover] [role=group] button')].find((button) => button.textContent.trim() === 'Hari ini').click();

                return true;
            })()
            JS)
        ->wait(0.6)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-range]');
                const buttons = [...root.querySelectorAll('[data-ui-popover] [role=group] button')];

                return buttons.find((button) => button.textContent.trim() === 'Hari ini').getAttribute('aria-pressed') === 'true' &&
                    buttons.filter((button) => button.textContent.trim() !== 'Hari ini').every((button) => button.getAttribute('aria-pressed') === 'false');
            })()
            JS);
});

it('opens to an enabled day when the value is out of bounds', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-out-of-bounds]');
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-out-of-bounds]');
                const grid = root.querySelector('[data-ui-popover] [role=grid]');

                return grid.getAttribute('aria-label') === 'Februari 2026' &&
                    document.activeElement.closest('td')?.dataset.date === '2026-02-10' &&
                    document.activeElement.disabled === false;
            })()
            JS);
});

it('dispatches lifecycle events without a value detail', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-bounded]');

                window.__openDetail = null;
                root.addEventListener('open', (event) => {
                    window.__openDetail = { keys: Object.keys(event.detail), hasValue: 'value' in event.detail };
                }, { once: true });
                root.querySelector('[data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript('window.__openDetail.hasValue === false && window.__openDetail.keys.length === 0');
});

it('shows full preset labels in a grid on mobile', function () {
    visit('/ui/date-picker')
        ->on()
        ->mobile()
        ->assertScript(<<<'JS'
            (() => {
                document.querySelector('[data-ui-demo=date-picker-range] [data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ui-demo=date-picker-range] [data-ui-popover]');
                const group = popover.querySelector('[data-ui-presets]');
                const buttons = [...group.querySelectorAll('button')];

                return getComputedStyle(group).display === 'grid' &&
                    getComputedStyle(group).gridTemplateColumns.split(' ').length === 2 &&
                    buttons.length > 0 &&
                    buttons.every((button) => getComputedStyle(button).textOverflow !== 'ellipsis' &&
                        button.scrollWidth <= button.clientWidth + 1);
            })()
            JS);
});
