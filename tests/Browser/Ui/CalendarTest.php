<?php

declare(strict_types=1);

it('has no serious accessibility issues on the calendar gallery', function (string $device) {
    visit('/ui/calendar')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('selects a single date and updates the model', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-single] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();
            window.__selectedDate = cells[10].dataset.date;

            return true;
        })()
        JS)
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single]');
                const selected = root.querySelector('[data-selected]');

                return selected !== null && selected.dataset.date === window.__selectedDate && root.querySelector('[data-ui-bound=date]').textContent.trim() === window.__selectedDate;
            })()
            JS);
});

it('moves and selects with the keyboard', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const selected = root.querySelector('[data-selected] button');

            window.__beforeDate = root.querySelector('[data-ui-bound=date]').textContent.trim();
            selected.focus();
            selected.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single]');
                const selected = root.querySelector('[data-selected]');
                const date = new Date(window.__beforeDate + 'T00:00:00Z');

                date.setUTCDate(date.getUTCDate() + 1);

                const expected = date.toISOString().slice(0, 10);

                return selected !== null &&
                    selected.dataset.date === expected &&
                    root.querySelector('[data-ui-bound=date]').textContent.trim() === expected;
            })()
            JS);
});

it('navigates between months', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            window.__beforeHeading = document.querySelector('[data-ui-demo=calendar-single] [data-ui-heading] span').textContent;

            return true;
        })()
        JS)
        ->click('[data-ui-demo="calendar-single"] button[aria-label="Bulan berikutnya"]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-single] [data-ui-heading] span').textContent !== window.__beforeHeading");
});

it('toggles multiple dates', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-multiple] td[data-date]:not([data-outside])')];

            cells[5].querySelector('button').click();
            cells[8].querySelector('button').click();
            window.__multipleDates = [cells[5].dataset.date, cells[8].dataset.date];

            return true;
        })()
        JS)
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-multiple]');
                const selected = [...root.querySelectorAll('[data-selected]')].map((cell) => cell.dataset.date);
                const bound = root.querySelector('[data-ui-bound=dates]').textContent;

                return selected.length === 2 && window.__multipleDates.every((date) => selected.includes(date) && bound.includes(date));
            })()
            JS);
});

it('selects a range and marks the days between', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();
            cells[14].querySelector('button').click();
            window.__rangeDates = [cells[10].dataset.date, cells[14].dataset.date];

            return true;
        })()
        JS)
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-range]');
                const start = root.querySelector('[data-start]');
                const end = root.querySelector('[data-end]');
                const inRange = [...root.querySelectorAll('[data-in-range]')].map((cell) => cell.dataset.date);

                return start !== null && end !== null && start.dataset.date === window.__rangeDates[0] && end.dataset.date === window.__rangeDates[1] && inRange.length === 3;
            })()
            JS);
});

it('applies min, max, unavailable, start-day and fixed weeks', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-constrained]');
            const headers = [...root.querySelectorAll('th')].map((header) => header.textContent.trim());
            const unavailable = [...root.querySelectorAll('td[data-unavailable]:not([data-outside])')];

            return root.querySelectorAll('tbody tr').length === 6 && headers[0] === 'Sen' && unavailable.length > 0;
        })()
        JS);
});

it('does not select dates in static mode', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-static]');
            const cell = root.querySelector('td[data-date]:not([data-outside]) button');

            cell.click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript("document.querySelectorAll('[data-ui-demo=calendar-static] [data-selected]').length === 0");
});

it('reflects a server-side single date update', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            window.__beforeDate = document.querySelector('[data-ui-demo=calendar-single] [data-ui-bound=date]').textContent.trim();

            return true;
        })()
        JS)
        ->click('[data-ui-test=set-date]')
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single]');
                const selected = root.querySelector('[data-selected]');
                const bound = root.querySelector('[data-ui-bound=date]').textContent.trim();

                return bound !== window.__beforeDate &&
                    selected !== null &&
                    selected.dataset.date === bound;
            })()
            JS);
});

it('binds a Carbon single date', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-carbon] td[data-date]:not([data-outside])')];

            cells[12].querySelector('button').click();
            window.__carbonDate = cells[12].dataset.date;

            return true;
        })()
        JS)
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-carbon] [data-ui-bound=carbon-date]').textContent.trim() === window.__carbonDate");
});

it('exposes the invalid state', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-invalid] [data-ui-calendar]');
                const shadow = getComputedStyle(root).boxShadow;
                const colors = shadow.match(/(?:rgba?|oklch|oklab|hsla?|lab|lch|color)\([^)]*\)/g) ?? [];
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = 1;
                const context = canvas.getContext('2d', { willReadFrequently: true });
                const background = getComputedStyle(document.body).backgroundColor;
                const pixel = (paint) => {
                    context.clearRect(0, 0, 1, 1);
                    context.fillStyle = paint;
                    context.fillRect(0, 0, 1, 1);

                    return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
                };
                const composite = (paint) => {
                    context.clearRect(0, 0, 1, 1);
                    context.fillStyle = background;
                    context.fillRect(0, 0, 1, 1);
                    context.fillStyle = paint;
                    context.fillRect(0, 0, 1, 1);

                    return [...context.getImageData(0, 0, 1, 1).data].slice(0, 3);
                };
                const luminance = (values) => {
                    const [r, g, b] = values.map((channel) => {
                        const value = channel / 255;

                        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
                    });

                    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
                };
                const contrast = (left, right) => {
                    const lighter = Math.max(luminance(left), luminance(right));
                    const darker = Math.min(luminance(left), luminance(right));

                    return (lighter + 0.05) / (darker + 0.05);
                };
                const base = pixel(background);
                const best = colors
                    .map((color) => {
                        const painted = composite(color);

                        return { pixel: painted, ratio: contrast(painted, base) };
                    })
                    .sort((left, right) => right.ratio - left.ratio)[0];

                return root.hasAttribute('data-invalid') &&
                    root.querySelector('[role=grid]').getAttribute('aria-invalid') === 'true' &&
                    best !== undefined &&
                    best.ratio >= 3 &&
                    best.pixel[0] > best.pixel[1] &&
                    best.pixel[0] > best.pixel[2];
            })()
            JS);
});

it('renders two months and disables duplicate outside days', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-range]');

            const single = document.querySelector('[data-ui-demo=calendar-typed-range]');

            return root.querySelectorAll('[role=grid]').length === 2 &&
                root.querySelectorAll('td[data-outside][aria-disabled=true]').length > 0 &&
                single.querySelectorAll('[role=grid]').length === 1;
        })()
        JS);
});

it('shows the empty state for a too-short range and rejects unavailable crossings', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

                cells[10].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-range] [role=status].text-red-600').textContent.trim() === 'Pilih minimal 2 hari'")
        ->assertScript(<<<'JS'
            (() => {
                const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];
                const index = cells.findIndex((cell, position) => cell.hasAttribute('data-unavailable') && position > 0 && position < cells.length - 1);

                if (index < 0) {
                    return false;
                }

                cells[index - 1].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];
                const index = cells.findIndex((cell, position) => cell.hasAttribute('data-unavailable') && position > 0 && position < cells.length - 1);

                cells[index + 1].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-range] [role=status].text-red-600').textContent.trim() === 'Rentang terpilih memuat tanggal yang tidak tersedia'");
});

it('previews a range on hover without committing it', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

                cells[14].querySelector('button').dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-range]');

                return root.querySelector('[data-start]') !== null && root.querySelector('[data-end]') === null && root.querySelectorAll('[data-in-range]').length > 0;
            })()
            JS);
});

it('navigates to today and respects open-to', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-open-to]');
            const first = root.querySelector('td[data-date]:not([data-outside])');
            const now = new Date();
            const expected = new Date(Date.UTC(now.getFullYear(), now.getMonth() + 1, 1));
            const prefix = expected.getUTCFullYear() + '-' + String(expected.getUTCMonth() + 1).padStart(2, '0');

            return first !== null && first.dataset.date.startsWith(prefix);
        })()
        JS);

    $page->click('[data-ui-demo="calendar-single"] button[aria-label="Bulan berikutnya"]')
        ->wait(0.3)
        ->click('[data-ui-demo="calendar-single"] button[aria-label="Hari ini"]')
        ->wait(0.3)
        ->click('[data-ui-demo="calendar-single"] button[aria-label="Hari ini"]')
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single]');
                const selected = root.querySelector('[data-selected]');

                return selected !== null && selected.hasAttribute('data-today');
            })()
            JS);
});

it('hides navigation controls when navigation is false', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const buttons = [...document.querySelectorAll('[data-ui-demo=calendar-static] button[aria-label="Bulan sebelumnya"], [data-ui-demo=calendar-static] button[aria-label="Bulan berikutnya"]')];

                return buttons.length > 0 && buttons.every((button) => button.offsetParent === null);
            })()
            JS);
});

it('rejects a range longer than max-range and handles reverse clicks', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

                cells[25].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-range] [role=status].text-red-600').textContent.trim() === 'Pilih maksimal 10 hari'");

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[14].querySelector('button').click();

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

                cells[10].querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-range]');
                const start = root.querySelector('[data-start]');

                return start !== null && start.dataset.date === [...root.querySelectorAll('td[data-date]:not([data-outside])')][10].dataset.date && root.querySelector('[data-end]') === null;
            })()
            JS);
});

it('supports Home, End and PageDown keyboard navigation', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const selected = root.querySelector('[data-selected] button');

            selected.focus();
            selected.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));

            return true;
        })()
        JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const active = document.activeElement.closest('td');

                return active !== null && active.hasAttribute('data-active');
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.2)
        ->assertScript("document.activeElement.closest('td').cellIndex === 6")
        ->assertScript(<<<'JS'
            (() => {
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'PageDown', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                window.__headingBeforePageDown = document.querySelector('[data-ui-demo=calendar-single] [data-ui-heading] span').textContent;
                document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'PageDown', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-single] [data-ui-heading] span').textContent !== window.__headingBeforePageDown");
});

it('keeps day and toolbar targets touch-sized on mobile', function () {
    $page = visit('/ui/calendar')->on()->mobile();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const day = root.querySelector('td[data-date] button');
            const nav = root.querySelector('button[aria-label="Bulan berikutnya"]');
            const today = root.querySelector('button[aria-label="Hari ini"]');

            return day.getBoundingClientRect().height >= 44 && nav.getBoundingClientRect().height >= 44 && today.getBoundingClientRect().height >= 44;
        })()
        JS);
});

it('distinguishes out-of-bounds dates from unavailable dates', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-constrained]');
                const unavailable = [...root.querySelectorAll('td[data-unavailable]')];
                const disabled = [...root.querySelectorAll('td[aria-disabled=true]')];

                return unavailable.length === 1 && disabled.length > 1;
            })()
            JS);
});

it('rejects a range longer than a year when it crosses unavailable', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-long-range] [data-ui-calendar]');
            const data = window.Alpine.$data(root);

            data.rangeStart = '2026-01-01';
            data.rangeEnd = null;
            data.viewYear = 2027;
            data.viewMonth = 6;
            data.refresh();

            return true;
        })()
        JS)
        ->wait(0.2)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-long-range] [data-ui-calendar]');
                const cell = root.querySelector('td[data-date="2027-07-15"] button');

                if (cell === null) {
                    return false;
                }

                cell.click();

                return true;
            })()
            JS)
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-long-range] [role=status].text-red-600').textContent.trim() === 'Rentang terpilih memuat tanggal yang tidak tersedia'");
});

it('disables min/max bounds and unavailable separately', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-bounds]');
                const cell = (key) => root.querySelector('td[data-date="' + key + '"]');

                return cell('2024-02-09').getAttribute('aria-disabled') === 'true' &&
                    !cell('2024-02-09').hasAttribute('data-unavailable') &&
                    cell('2024-02-10').getAttribute('aria-disabled') === null &&
                    cell('2024-02-20').getAttribute('aria-disabled') === null &&
                    cell('2024-02-21').getAttribute('aria-disabled') === 'true' &&
                    cell('2024-02-15').hasAttribute('data-unavailable');
            })()
            JS);
});

it('renders plain-form values for multiple and range modes', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-plain]');
                const multiple = [...root.querySelectorAll('input[name^=plain_dates]')].map((input) => input.value).sort();
                const start = root.querySelector('input[name^=plain_range][name$="[start]"]');
                const end = root.querySelector('input[name^=plain_range][name$="[end]"]');

                return multiple.length === 2 && multiple.join('|') === '2024-02-10|2024-02-12' && start.value === '2024-02-10' && end.value === '2024-02-14';
            })()
            JS);
});

it('crosses between two visible months without shifting the view', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-range]');
            const first = root.querySelectorAll('[role=grid]')[0];
            const cells = [...first.querySelectorAll('td[data-date]:not([data-outside])')];
            const last = cells[cells.length - 1].querySelector('button');

            window.__headingBeforeCrossing = root.querySelector('[data-ui-heading] span').textContent;
            last.focus();
            last.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

            return true;
        })()
        JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-range]');
                const grids = root.querySelectorAll('[role=grid]');
                const active = document.activeElement;

                return active.closest('[role=grid]') === grids[1] && root.querySelector('[data-ui-heading] span').textContent === window.__headingBeforeCrossing;
            })()
            JS);
});

it('updates plain-form hidden fields after selection', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=calendar-plain]');
            const calendars = root.querySelectorAll('[data-ui-calendar]');
            const multiple = [...calendars[1].querySelectorAll('td[data-date]:not([data-outside])')];
            const range = [...calendars[2].querySelectorAll('td[data-date]:not([data-outside])')];

            multiple[20].querySelector('button').click();
            range[20].querySelector('button').click();
            range[22].querySelector('button').click();
            window.__plainMultiple = multiple[20].dataset.date;
            window.__plainRange = [range[20].dataset.date, range[22].dataset.date];

            return true;
        })()
        JS)
        ->wait(0.4)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-plain]');
                const multiple = [...root.querySelectorAll('input[name^=plain_dates]')].map((input) => input.value);
                const start = root.querySelector('input[name^=plain_range][name$="[start]"]').value;
                const end = root.querySelector('input[name^=plain_range][name$="[end]"]').value;

                return multiple.includes(window.__plainMultiple) && start === window.__plainRange[0] && end === window.__plainRange[1];
            })()
            JS);
});

it('binds a typed Flux DateRange', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript(<<<'JS'
        (() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-typed-range] td[data-date]:not([data-outside])')];

            cells[6].querySelector('button').click();
            cells[10].querySelector('button').click();
            window.__typedRange = [cells[6].dataset.date, cells[10].dataset.date];

            return true;
        })()
        JS)
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const bound = document.querySelector('[data-ui-demo=calendar-typed-range] [data-ui-bound=typed-range]').textContent.trim();

                return bound === window.__typedRange[0] + ' / ' + window.__typedRange[1];
            })()
            JS);
});

it('renders one centered heading for a two-month calendar', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-range] [data-ui-calendar]');
                const headings = root.querySelectorAll('[data-ui-heading]');

                return headings.length === 1 &&
                    headings[0].textContent.includes('–') &&
                    getComputedStyle(headings[0]).textAlign === 'center';
            })()
            JS);
});

it('keeps the year select from equalizing the heading side tracks', function () {
    visit('/ui/date-picker')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                document.querySelector('[data-ui-demo=date-picker-single] [data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=date-picker-single]');
                const heading = root.querySelector('[data-ui-heading]');
                const grid = heading.parentElement;
                const tracks = getComputedStyle(grid).gridTemplateColumns.split(' ').map(parseFloat);
                const headingRect = heading.getBoundingClientRect();
                const gridRect = grid.getBoundingClientRect();

                return tracks.length === 3 &&
                    tracks[0] < tracks[1] &&
                    tracks[2] < tracks[1] &&
                    Math.abs(headingRect.left + headingRect.width / 2 - (gridRect.left + gridRect.width / 2)) < 1;
            })()
            JS);
});

it('offers a year select from 1970 through five years ahead', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single] [data-ui-calendar]');
                const select = root.querySelector('[data-ui-heading] select');
                const years = [...select.options].map((option) => Number(option.value));
                const current = new Date().getFullYear();

                return select.getAttribute('aria-label') === 'Tahun' &&
                    years[0] === 1970 &&
                    years[years.length - 1] === current + 5 &&
                    Number(select.value) === current;
            })()
            JS);
});

it('changes the year without changing the month', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single] [data-ui-calendar]');
                const select = root.querySelector('[data-ui-heading] select');
                window.__monthBeforeYear = root.querySelector('[data-ui-heading] span').textContent.trim();

                select.value = '2030';
                select.dispatchEvent(new Event('change', { bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-single] [data-ui-calendar]');
                const select = root.querySelector('[data-ui-heading] select');
                const first = root.querySelector('td[data-date]:not([data-outside])');

                return select.value === '2030' &&
                    root.querySelector('[data-ui-heading] span').textContent.trim() === window.__monthBeforeYear &&
                    first.dataset.date.startsWith('2030-');
            })()
            JS);
});

it('limits year options to the calendar bounds', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=calendar-constrained] [data-ui-calendar]');
                const years = [...root.querySelector('[data-ui-heading] select').options].map((option) => Number(option.value));

                return years.length === 1 && years[0] === new Date().getFullYear();
            })()
            JS);
});
