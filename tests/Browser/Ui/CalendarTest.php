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

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-single] td[data-date]:not([data-outside])')];

        cells[10].querySelector('button').click();
        window.__selectedDate = cells[10].dataset.date;

        return true;
    })()")
        ->wait(0.8)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const selected = root.querySelector('[data-selected]');

            return selected !== null && selected.dataset.date === window.__selectedDate && root.querySelector('[data-ui-bound=date]').textContent.trim() === window.__selectedDate;
        })()");
});

it('moves and selects with the keyboard', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-single]');
        const selected = root.querySelector('[data-selected] button');

        selected.focus();
        selected.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

            return true;
        })()")
        ->wait(0.8)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const selected = root.querySelector('[data-selected]');

            return selected !== null && root.querySelector('[data-ui-bound=date]').textContent.trim() === selected.dataset.date;
        })()");
});

it('navigates between months', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        window.__beforeHeading = document.querySelector('[data-ui-demo=calendar-single] [x-text=heading]').textContent;

        return true;
    })()")
        ->click('[data-ui-demo="calendar-single"] button[aria-label="Bulan berikutnya"]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-single] [x-text=heading]').textContent !== window.__beforeHeading");
});

it('toggles multiple dates', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-multiple] td[data-date]:not([data-outside])')];

        cells[5].querySelector('button').click();
        cells[8].querySelector('button').click();
        window.__multipleDates = [cells[5].dataset.date, cells[8].dataset.date];

        return true;
    })()")
        ->wait(0.8)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-multiple]');
            const selected = [...root.querySelectorAll('[data-selected]')].map((cell) => cell.dataset.date);
            const bound = root.querySelector('[data-ui-bound=dates]').textContent;

            return selected.length === 2 && window.__multipleDates.every((date) => selected.includes(date) && bound.includes(date));
        })()");
});

it('selects a range and marks the days between', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

        cells[10].querySelector('button').click();
        cells[14].querySelector('button').click();
        window.__rangeDates = [cells[10].dataset.date, cells[14].dataset.date];

        return true;
    })()")
        ->wait(0.8)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-range]');
            const start = root.querySelector('[data-start]');
            const end = root.querySelector('[data-end]');
            const inRange = [...root.querySelectorAll('[data-in-range]')].map((cell) => cell.dataset.date);

            return start !== null && end !== null && start.dataset.date === window.__rangeDates[0] && end.dataset.date === window.__rangeDates[1] && inRange.length === 3;
        })()");
});

it('applies min, max, unavailable, start-day and fixed weeks', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-constrained]');
        const headers = [...root.querySelectorAll('th')].map((header) => header.textContent.trim());
        const unavailable = [...root.querySelectorAll('td[data-unavailable]:not([data-outside])')];

        return root.querySelectorAll('tbody tr').length === 6 && headers[0] === 'Sen' && unavailable.length > 0;
    })()");
});

it('does not select dates in static mode', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-static]');
        const cell = root.querySelector('td[data-date]:not([data-outside]) button');

        cell.click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("document.querySelectorAll('[data-ui-demo=calendar-static] [data-selected]').length === 0");
});

it('reflects a server-side single date update', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        window.__beforeDate = document.querySelector('[data-ui-demo=calendar-single] [data-ui-bound=date]').textContent.trim();

        return true;
    })()")
        ->click('[data-ui-test=set-date]')
        ->wait(0.8)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const selected = root.querySelector('[data-selected]');

            return root.querySelector('[data-ui-bound=date]').textContent.trim() !== window.__beforeDate && selected !== null;
        })()");
});

it('binds a Carbon single date', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-carbon] td[data-date]:not([data-outside])')];

        cells[12].querySelector('button').click();
        window.__carbonDate = cells[12].dataset.date;

        return true;
    })()")
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-carbon] [data-ui-bound=carbon-date]').textContent.trim() === window.__carbonDate");
});

it('exposes the invalid state', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-invalid] [data-ui-calendar]');

            return root.hasAttribute('data-invalid') && root.querySelector('[role=grid]').getAttribute('aria-invalid') === 'true';
        })()");
});

it('renders two months and disables duplicate outside days', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-range]');

        return root.querySelectorAll('[role=grid]').length === 2 && root.querySelectorAll('td[data-outside][aria-disabled=true]').length > 0;
    })()");
});

it('shows the empty state for a too-short range and rejects unavailable crossings', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

        cells[10].querySelector('button').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-range] [role=status].text-red-600').textContent.trim() === 'Pilih minimal 2 hari'")
        ->assertScript("(() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];
            const index = cells.findIndex((cell, position) => cell.hasAttribute('data-unavailable') && position > 0 && position < cells.length - 1);

            if (index < 0) {
                return false;
            }

            cells[index - 1].querySelector('button').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];
            const index = cells.findIndex((cell, position) => cell.hasAttribute('data-unavailable') && position > 0 && position < cells.length - 1);

            cells[index + 1].querySelector('button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-range] [role=status].text-red-600').textContent.trim() === 'Rentang terpilih memuat tanggal yang tidak tersedia'");
});

it('previews a range on hover without committing it', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

        cells[10].querySelector('button').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[14].querySelector('button').dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-range]');

            return root.querySelector('[data-start]') !== null && root.querySelector('[data-end]') === null && root.querySelectorAll('[data-in-range]').length > 0;
        })()");
});

it('navigates to today and respects open-to', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-open-to]');
        const first = root.querySelector('td[data-date]:not([data-outside])');
        const now = new Date();
        const expected = new Date(Date.UTC(now.getFullYear(), now.getMonth() + 1, 1));
        const prefix = expected.getUTCFullYear() + '-' + String(expected.getUTCMonth() + 1).padStart(2, '0');

        return first !== null && first.dataset.date.startsWith(prefix);
    })()");

    $page->click('[data-ui-demo="calendar-single"] button[aria-label="Bulan berikutnya"]')
        ->wait(0.3)
        ->click('[data-ui-demo="calendar-single"] button[aria-label="Hari ini"]')
        ->wait(0.3)
        ->click('[data-ui-demo="calendar-single"] button[aria-label="Hari ini"]')
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-single]');
            const selected = root.querySelector('[data-selected]');

            return selected !== null && selected.hasAttribute('data-today');
        })()");
});

it('hides navigation controls when navigation is false', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const buttons = [...document.querySelectorAll('[data-ui-demo=calendar-static] button[aria-label=\"Bulan sebelumnya\"], [data-ui-demo=calendar-static] button[aria-label=\"Bulan berikutnya\"]')];

            return buttons.length > 0 && buttons.every((button) => button.offsetParent === null);
        })()");
});

it('renders leap and natural four-week grids', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const leap = document.querySelector('[data-ui-demo=calendar-leap]');
        const four = document.querySelector('[data-ui-demo=calendar-four-weeks]');

        return leap.querySelectorAll('td[data-date]:not([data-outside])').length === 29 &&
            leap.querySelectorAll('tbody tr').length === 5 &&
            four.querySelectorAll('td[data-date]:not([data-outside])').length === 28 &&
            four.querySelectorAll('tbody tr').length === 4;
    })()");
});

it('uses locale-derived and explicit week starts', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const english = document.querySelector('[data-ui-demo=calendar-en]');
            const constrained = document.querySelector('[data-ui-demo=calendar-constrained]');

            return english.querySelector('th').textContent.trim() === 'Sun' && constrained.querySelector('th').textContent.trim() === 'Sen';
        })()");
});

it('rejects a range longer than max-range and handles reverse clicks', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

        cells[10].querySelector('button').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[25].querySelector('button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-range] [role=status].text-red-600').textContent.trim() === 'Pilih maksimal 10 hari'");

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

        cells[14].querySelector('button').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const cells = [...document.querySelectorAll('[data-ui-demo=calendar-range] td[data-date]:not([data-outside])')];

            cells[10].querySelector('button').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-range]');
            const start = root.querySelector('[data-start]');

            return start !== null && start.dataset.date === [...root.querySelectorAll('td[data-date]:not([data-outside])')][10].dataset.date && root.querySelector('[data-end]') === null;
        })()");
});

it('supports Home, End and PageDown keyboard navigation', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-single]');
        const selected = root.querySelector('[data-selected] button');

        selected.focus();
        selected.dispatchEvent(new KeyboardEvent('keydown', { key: 'Home', bubbles: true }));

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const active = document.activeElement.closest('td');

            return active !== null && active.hasAttribute('data-active');
        })()")
        ->assertScript("(() => {
            document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'End', bubbles: true }));

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("document.activeElement.closest('td').cellIndex === 6")
        ->assertScript("(() => {
            document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'PageDown', bubbles: true }));

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            window.__headingBeforePageDown = document.querySelector('[data-ui-demo=calendar-single] [x-text=heading]').textContent;
            document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'PageDown', bubbles: true }));

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-single] [x-text=heading]').textContent !== window.__headingBeforePageDown");
});

it('keeps day and toolbar targets touch-sized on mobile', function () {
    $page = visit('/ui/calendar')->on()->mobile();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-single]');
        const day = root.querySelector('td[data-date] button');
        const nav = root.querySelector('button[aria-label=\"Bulan berikutnya\"]');
        const today = root.querySelector('button[aria-label=\"Hari ini\"]');

        return day.getBoundingClientRect().height >= 44 && nav.getBoundingClientRect().height >= 44 && today.getBoundingClientRect().height >= 44;
    })()");
});

it('distinguishes out-of-bounds dates from unavailable dates', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-constrained]');
            const unavailable = [...root.querySelectorAll('td[data-unavailable]')];
            const disabled = [...root.querySelectorAll('td[aria-disabled=true]')];

            return unavailable.length === 1 && disabled.length > 1;
        })()");
});

it('renders a plain-form hidden value', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("document.querySelector('[data-ui-demo=calendar-plain] input[name=plain_date]').value === '2024-02-15'");
});

it('rejects a range longer than a year when it crosses unavailable', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-long-range] [data-ui-calendar]');
        const data = window.Alpine.\$data(root);

        data.rangeStart = '2026-01-01';
        data.rangeEnd = null;
        data.viewYear = 2027;
        data.viewMonth = 6;
        data.refresh();

        return true;
    })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-long-range] [data-ui-calendar]');
            const cell = root.querySelector('td[data-date=\"2027-07-15\"] button');

            if (cell === null) {
                return false;
            }

            cell.click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=calendar-long-range] [role=status].text-red-600').textContent.trim() === 'Rentang terpilih memuat tanggal yang tidak tersedia'");
});

it('disables min/max bounds and unavailable separately', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-bounds]');
            const cell = (key) => root.querySelector('td[data-date=\"' + key + '\"]');

            return cell('2024-02-09').getAttribute('aria-disabled') === 'true' &&
                !cell('2024-02-09').hasAttribute('data-unavailable') &&
                cell('2024-02-10').getAttribute('aria-disabled') === null &&
                cell('2024-02-20').getAttribute('aria-disabled') === null &&
                cell('2024-02-21').getAttribute('aria-disabled') === 'true' &&
                cell('2024-02-15').hasAttribute('data-unavailable');
        })()");
});

it('falls back to supported week starts when Intl week info is unavailable', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const roots = {
                ca: document.querySelector('[data-ui-demo=calendar-en] [data-ui-calendar]'),
                ph: document.querySelector('[data-ui-demo=calendar-fallback-sunday] [data-ui-calendar]'),
                en: document.querySelector('[data-ui-demo=calendar-fallback-en] [data-ui-calendar]'),
                gb: document.querySelector('[data-ui-demo=calendar-fallback-monday] [data-ui-calendar]'),
                sa: document.querySelector('[data-ui-demo=calendar-fallback-ar-sa] [data-ui-calendar]'),
                eg: document.querySelector('[data-ui-demo=calendar-fallback-ar-eg] [data-ui-calendar]'),
            };
            const original = Intl.Locale;

            Intl.Locale = function () {
                return {};
            };

            Object.values(roots).forEach((root) => {
                window.Alpine.destroyTree(root);
                window.Alpine.initTree(root);
            });

            Intl.Locale = original;

            return new Promise((resolve) =>
                setTimeout(() => resolve(
                    roots.ca.querySelector('th').textContent.trim() === 'Sun' &&
                    roots.ph.querySelector('th').textContent.trim() === 'Sun' &&
                    roots.en.dataset.startDay === '0' &&
                    roots.gb.querySelector('th').textContent.trim() === 'Mon' &&
                    roots.sa.dataset.startDay === '0' &&
                    roots.eg.dataset.startDay === '6'
                ), 100)
            );
        })()");
});

it('renders plain-form values for multiple and range modes', function () {
    visit('/ui/calendar')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-plain]');
            const multiple = [...root.querySelectorAll('input[name^=plain_dates]')].map((input) => input.value).sort();
            const start = root.querySelector('input[name^=plain_range][name$=\"[start]\"]');
            const end = root.querySelector('input[name^=plain_range][name$=\"[end]\"]');

            return multiple.length === 2 && multiple.join('|') === '2024-02-10|2024-02-12' && start.value === '2024-02-10' && end.value === '2024-02-14';
        })()");
});

it('crosses between two visible months without shifting the view', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=calendar-range]');
        const first = root.querySelectorAll('[role=grid]')[0];
        const cells = [...first.querySelectorAll('td[data-date]:not([data-outside])')];
        const last = cells[cells.length - 1].querySelector('button');

        window.__headingBeforeCrossing = root.querySelector('[x-text=heading]').textContent;
        last.focus();
        last.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-range]');
            const grids = root.querySelectorAll('[role=grid]');
            const active = document.activeElement;

            return active.closest('[role=grid]') === grids[1] && root.querySelector('[x-text=heading]').textContent === window.__headingBeforeCrossing;
        })()");
});

it('updates plain-form hidden fields after selection', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
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
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=calendar-plain]');
            const multiple = [...root.querySelectorAll('input[name^=plain_dates]')].map((input) => input.value);
            const start = root.querySelector('input[name^=plain_range][name$=\"[start]\"]').value;
            const end = root.querySelector('input[name^=plain_range][name$=\"[end]\"]').value;

            return multiple.includes(window.__plainMultiple) && start === window.__plainRange[0] && end === window.__plainRange[1];
        })()");
});

it('binds a typed Flux DateRange', function () {
    $page = visit('/ui/calendar')->on()->desktop();

    $page->assertScript("(() => {
        const cells = [...document.querySelectorAll('[data-ui-demo=calendar-typed-range] td[data-date]:not([data-outside])')];

        cells[6].querySelector('button').click();
        cells[10].querySelector('button').click();
        window.__typedRange = [cells[6].dataset.date, cells[10].dataset.date];

        return true;
    })()")
        ->wait(0.8)
        ->assertScript("(() => {
            const bound = document.querySelector('[data-ui-demo=calendar-typed-range] [data-ui-bound=typed-range]').textContent.trim();

            return bound === window.__typedRange[0] + ' / ' + window.__typedRange[1];
        })()");
});
