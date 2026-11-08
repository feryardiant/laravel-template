<?php

declare(strict_types=1);

it('has no serious accessibility issues on the datetime picker gallery', function (string $device) {
    visit('/ui/datetime-picker')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues while the popover is open', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=datetime-picker-range] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertNoAccessibilityIssues();
});

it('opens on the selected date, time and formatted trigger value', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=datetime-picker-single] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
            const trigger = root.querySelector('[data-ui-trigger]');
            const popover = root.querySelector('[data-ui-popover]');

            return popover.matches(':popover-open') &&
                trigger.getAttribute('aria-expanded') === 'true' &&
                document.activeElement.closest('td')?.dataset.date === '2026-11-08' &&
                trigger.value.includes('8 Nov 2026') &&
                trigger.value.includes('10.30') &&
                popover.querySelectorAll('[data-ui-time-columns]').length === 1;
        })()");
});

it('commits a date change immediately and applies a time change on close', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-single] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
            root.querySelector('[data-ui-popover] td[data-date=\"2026-11-12\"] button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');

            return root.querySelector('[data-ui-bound=start-at]').textContent.trim() === '2026-11-12T10:30' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === true;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
            root.querySelector('[data-ui-popover] [data-endpoint=start] [data-column=minute] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');

            return root.querySelector('[data-ui-bound=start-at]').textContent.trim() === '2026-11-12T10:00' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                document.activeElement === root.querySelector('[data-ui-trigger]');
        })()");
});

it('uses the default time when a date is picked without a value', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=datetime-picker-empty] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-empty]');
            root.querySelector('[data-ui-popover] td[data-date=\"2026-11-15\"] button').click();

            return true;
        })()")
        ->wait(0.5)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-empty]');

            return root.querySelector('[data-ui-bound=empty-at]').textContent.trim() === '2026-11-15T09:00';
        })()");
});

it('requires the footer in single mode when confirmation is enabled', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-confirm] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-confirm]');
            root.querySelector('[data-ui-popover] [data-endpoint=start] [data-column=minute] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-confirm]');

            return root.querySelector('[data-ui-bound=confirmed-at]').textContent.trim() === '2026-11-08T10:30' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === true;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-confirm]');
            [...root.querySelectorAll('[data-ui-popover] .border-t button')].find((button) => button.textContent.trim() === 'Pilih').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-confirm]');

            return root.querySelector('[data-ui-bound=confirmed-at]').textContent.trim() === '2026-11-08T10:00' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('previews a range and applies it only with the footer', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-range] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            window.__rangeBefore = root.querySelector('[data-ui-bound=schedule]').textContent.trim();

            return root.querySelector('[data-ui-popover]').querySelectorAll('[role=grid]').length === 2 &&
                root.querySelectorAll('[data-ui-popover] [data-ui-time-columns]').length === 2;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            const popover = root.querySelector('[data-ui-popover]');
            popover.querySelector('td[data-date=\"2026-11-12\"] button').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            const popover = root.querySelector('[data-ui-popover]');
            popover.querySelector('td[data-date=\"2026-11-14\"] button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            const confirm = [...root.querySelectorAll('[data-ui-popover] .border-t button')].find((button) => button.textContent.trim() === 'Pilih rentang');

            return root.querySelector('[data-ui-bound=schedule]').textContent.trim() === window.__rangeBefore && ! confirm.disabled;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            [...root.querySelectorAll('[data-ui-popover] .border-t button')].find((button) => button.textContent.trim() === 'Pilih rentang').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');

            return root.querySelector('[data-ui-bound=schedule]').textContent.trim() === '2026-11-12T09:00 / 2026-11-14T17:00' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('uses the default start and end times for a new range', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-range-empty] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range-empty]');
            const popover = root.querySelector('[data-ui-popover]');
            popover.querySelector('td[data-date=\"2026-11-12\"] button').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range-empty]');
            const popover = root.querySelector('[data-ui-popover]');
            popover.querySelector('td[data-date=\"2026-11-14\"] button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range-empty]');
            const popover = root.querySelector('[data-ui-popover]');
            const startHour = popover.querySelector('[data-endpoint=start] [data-column=hour] [aria-selected=true]');
            const endHour = popover.querySelector('[data-endpoint=end] [data-column=hour] [aria-selected=true]');

            return startHour.dataset.hour === '9' && endHour.dataset.hour === '17';
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range-empty]');
            [...root.querySelectorAll('[data-ui-popover] .border-t button')].find((button) => button.textContent.trim() === 'Pilih rentang').click();

            return true;
        })()")
        ->wait(0.5)
        ->assertScript("window.Alpine.\$data(document.querySelector('[data-ui-demo=datetime-picker-range-empty] [data-ui-datetime-picker]')).valueStartTime === '09:00'");
});

it('discards a pending range on cancel and Escape', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
        window.__rangeCommit = root.querySelector('[data-ui-bound=schedule]').textContent.trim();
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            root.querySelector('[data-ui-popover] td[data-date=\"2026-11-20\"] button').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            [...root.querySelectorAll('[data-ui-popover] .border-t button')].find((button) => button.textContent.trim() === 'Batal').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');

            return root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                root.querySelector('[data-ui-bound=schedule]').textContent.trim() === window.__rangeCommit;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
            root.querySelector('[data-ui-popover] td[data-date=\"2026-11-22\"] button').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');

            return root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                root.querySelector('[data-ui-bound=schedule]').textContent.trim() === window.__rangeCommit;
        })()");
});

it('prevents a same-day end time before the start', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=datetime-picker-same-day] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-same-day]');
            const popover = root.querySelector('[data-ui-popover]');
            const hour = (endpoint, value) => popover.querySelector('[data-endpoint=' + endpoint + '] [data-column=hour] [data-hour=\"' + value + '\"]');

            return hour('end', '8').getAttribute('aria-disabled') === 'true' &&
                hour('end', '9').getAttribute('aria-disabled') === null &&
                hour('end', '10').getAttribute('aria-disabled') === null &&
                hour('start', '17').getAttribute('aria-disabled') === 'true' &&
                hour('start', '16').getAttribute('aria-disabled') === null;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-same-day]');
            root.querySelector('[data-ui-popover] [data-endpoint=end] [data-column=hour] [data-hour=\"9\"]').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-same-day]');
            const minute = (value) => root.querySelector('[data-ui-popover] [data-endpoint=end] [data-column=minute] [data-minute=\"' + value + '\"]');

            return minute('0').getAttribute('aria-disabled') === 'true' && minute('30').getAttribute('aria-disabled') === null;
        })()");
});

it('applies min, max, daily time windows and unavailable slots', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-constrained] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-constrained]');
            const popover = root.querySelector('[data-ui-popover]');
            const cell = (key) => popover.querySelector('td[data-date=\"' + key + '\"]');
            const hour = (value) => popover.querySelector('[data-endpoint=start] [data-column=hour] [data-hour=\"' + value + '\"]');

            return cell('2026-02-09').getAttribute('aria-disabled') === 'true' &&
                cell('2026-02-10').getAttribute('aria-disabled') === null &&
                cell('2026-02-17').hasAttribute('data-unavailable') &&
                cell('2026-02-20').getAttribute('aria-disabled') === null &&
                cell('2026-02-21').getAttribute('aria-disabled') === 'true' &&
                hour('8').getAttribute('aria-disabled') === 'true' &&
                hour('9').getAttribute('aria-disabled') === null &&
                hour('12').getAttribute('aria-disabled') === 'true' &&
                hour('17').getAttribute('aria-disabled') === null &&
                hour('18').getAttribute('aria-disabled') === 'true';
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-constrained]');
            const popover = root.querySelector('[data-ui-popover]');
            popover.querySelector('td[data-date=\"2026-02-18\"] button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-constrained]');
            root.querySelector('[data-ui-popover] [data-endpoint=start] [data-column=hour] [data-hour=\"10\"]').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-constrained]');
            const minute = (value) => root.querySelector('[data-ui-popover] [data-endpoint=start] [data-column=minute] [data-minute=\"' + value + '\"]');

            return minute('30').getAttribute('aria-disabled') === 'true' && minute('0').getAttribute('aria-disabled') === null;
        })()");
});

it('clears the value without opening the popover', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
            root.querySelector('[aria-label=\"Kosongkan tanggal dan waktu\"]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');

            return root.querySelector('[data-ui-bound=start-at]').textContent.trim() === '' &&
                root.querySelector('[data-ui-trigger]').value === '' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('navigates the calendar and time columns with the keyboard', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-single] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->keys('[data-ui-demo=datetime-picker-single] [data-ui-calendar] td[data-selected] button', ['ArrowRight'])
        ->wait(0.3)
        ->assertScript("(() => {
            document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=datetime-picker-single] [data-ui-bound=start-at]').textContent.trim() === '2026-11-09T10:30'")
        ->keys('[data-ui-demo=datetime-picker-single] [data-endpoint=start] [data-column=hour]', ['ArrowDown', 'Enter'])
        ->wait(0.3)
        ->keys('[data-ui-demo=datetime-picker-single] [data-endpoint=start] [data-column=minute]', ['ArrowDown', 'Enter'])
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');

            return root.querySelector('[data-ui-bound=start-at]').textContent.trim() === '2026-11-09T11:00' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('traps Tab focus inside the popover', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=datetime-picker-range] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-range]');
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
        })()");
});

it('writes plain-form hidden fields for single and range', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=datetime-picker-plain]');

        return root.querySelector('input[name=plain_at]').value === '2024-02-15T14:30' &&
            root.querySelector('input[name=\"plain_schedule[start]\"]').value === '2024-02-10T09:00' &&
            root.querySelector('input[name=\"plain_schedule[end]\"]').value === '2024-02-12T17:00';
    })()");

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=datetime-picker-plain]');
        root.querySelectorAll('[data-ui-datetime-picker]')[0].querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-plain]');
            const picker = root.querySelectorAll('[data-ui-datetime-picker]')[0];
            picker.querySelector('[data-ui-popover] td[data-date=\"2024-02-20\"] button').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-plain]');
            const picker = root.querySelectorAll('[data-ui-datetime-picker]')[0];
            picker.querySelector('[data-ui-popover] [data-endpoint=start] [data-column=minute] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("document.querySelector('[data-ui-demo=datetime-picker-plain] input[name=plain_at]').value === '2024-02-20T14:00'");
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/datetime-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
        window.__beforeDisplay = root.querySelector('[data-ui-trigger]').value;

        return true;
    })()")
        ->click('[data-ui-test=move-start]')
        ->wait(0.7)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
            const trigger = root.querySelector('[data-ui-trigger]');

            return root.querySelector('[data-ui-bound=start-at]').textContent.trim() === '2026-11-10T14:30' &&
                trigger.value !== window.__beforeDisplay;
        })()");
});

it('does not open when the picker is disabled', function () {
    visit('/ui/datetime-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-disabled]');
            const trigger = root.querySelector('[data-ui-trigger]');

            trigger.click();

            return trigger.disabled && root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('keeps touch targets and the popover within the mobile viewport', function () {
    $page = visit('/ui/datetime-picker')->on()->mobile();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=datetime-picker-single] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=datetime-picker-single]');
            const popover = root.querySelector('[data-ui-popover]');
            const trigger = root.querySelector('[data-ui-trigger]');
            const day = popover.querySelector('td[data-date] button');
            const hour = popover.querySelector('[data-endpoint=start] [data-column=hour] [role=option]');
            const rect = popover.getBoundingClientRect();

            return trigger.getBoundingClientRect().height >= 44 &&
                day.getBoundingClientRect().height >= 44 &&
                hour.getBoundingClientRect().height >= 44 &&
                Math.round(rect.left) === 8 &&
                Math.round(rect.width) === window.innerWidth - 16 &&
                rect.right <= window.innerWidth;
        })()");
});
