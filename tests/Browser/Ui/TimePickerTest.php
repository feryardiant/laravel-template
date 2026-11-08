<?php

declare(strict_types=1);

it('has no serious accessibility issues on the time picker gallery', function (string $device) {
    visit('/ui/time-picker')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('has no serious accessibility issues while the popover is open', function () {
    visit('/ui/time-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=time-picker-multiple] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertNoAccessibilityIssues();
});

it('opens the popover and focuses the hour listbox', function () {
    visit('/ui/time-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            document.querySelector('[data-ui-demo=time-picker-single] [data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            const trigger = root.querySelector('[data-ui-trigger]');
            const popover = root.querySelector('[data-ui-popover]');

            return popover.matches(':popover-open') &&
                trigger.getAttribute('aria-expanded') === 'true' &&
                document.activeElement.hasAttribute('data-ui-hours') &&
                document.activeElement.getAttribute('aria-activedescendant') !== null;
        })()");
});

it('applies a single time and closes', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-single]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-single]');
        const popover = root.querySelector('[data-ui-popover]');

        popover.querySelector('[data-ui-hours] [data-hour=\"10\"]').click();

        return true;
    })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            const popover = root.querySelector('[data-ui-popover]');

            popover.querySelector('[data-ui-minutes] [data-minute=\"30\"]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            const trigger = root.querySelector('[data-ui-trigger]');

            return root.querySelector('[data-ui-bound=time]').textContent.trim() === '10:30' &&
                trigger.value !== '' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                document.activeElement === trigger;
        })()");
});

it('shows twelve hours and toggles meridiem in 12-hour mode', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-12h]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-12h]');
            const popover = root.querySelector('[data-ui-popover]');
            const meridiem = [...popover.querySelectorAll('[aria-pressed]')];

            return popover.querySelectorAll('[data-ui-hours] [role=option]').length === 12 &&
                meridiem.find((button) => button.textContent.trim() === 'PM').getAttribute('aria-pressed') === 'true';
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-12h]');
            const popover = root.querySelector('[data-ui-popover]');

            [...popover.querySelectorAll('[aria-pressed]')].find((button) => button.textContent.trim() === 'AM').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-12h]');
            root.querySelector('[data-ui-popover] [data-ui-minutes] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-12h]');
            const trigger = root.querySelector('[data-ui-trigger]');

            return root.querySelector('[data-ui-bound=time12]').textContent.trim() === '01:00' &&
                trigger.value.includes('AM') &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('adds and removes times in multiple mode', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
            const popover = root.querySelector('[data-ui-popover]');

            return root.querySelector('[data-ui-trigger]').value === '2 dipilih' &&
                popover.querySelectorAll('[data-ui-selected] [data-time]').length === 2;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
            const popover = root.querySelector('[data-ui-popover]');

            popover.querySelector('[data-ui-hours] [data-hour=\"14\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
            const popover = root.querySelector('[data-ui-popover]');

            popover.querySelector('[data-ui-minutes] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
            const add = root.querySelector('[data-ui-popover] [data-ui-add]');

            if (add.disabled) {
                return false;
            }

            add.click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
            const popover = root.querySelector('[data-ui-popover]');

            return root.querySelector('[data-ui-bound=times]').textContent.trim() === '09:00, 11:30, 14:00' &&
                root.querySelector('[data-ui-trigger]').value === '3 dipilih' &&
                popover.matches(':popover-open') === true;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
            root.querySelector('[data-ui-popover] [data-ui-selected] [data-time=\"09:00\"]').click();

            return true;
        })()")
        ->wait(0.5)
        ->assertScript("document.querySelector('[data-ui-demo=time-picker-multiple] [data-ui-bound=times]').textContent.trim() === '11:30, 14:00'");
});

it('applies min, max and unavailable time ranges', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-constrained]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-constrained]');
            const popover = root.querySelector('[data-ui-popover]');
            const hour = (value) => popover.querySelector('[data-ui-hours] [data-hour=\"' + value + '\"]');

            return hour('8').getAttribute('aria-disabled') === 'true' &&
                hour('9').getAttribute('aria-disabled') === null &&
                hour('12').getAttribute('aria-disabled') === 'true' &&
                hour('17').getAttribute('aria-disabled') === null &&
                hour('18').getAttribute('aria-disabled') === 'true' &&
                popover.querySelectorAll('[data-ui-minutes] [role=option]').length === 4;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-constrained]');
            const popover = root.querySelector('[data-ui-popover]');

            popover.querySelector('[data-ui-hours] [data-hour=\"17\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-constrained]');
            const popover = root.querySelector('[data-ui-popover]');
            const minutes = [...popover.querySelectorAll('[data-ui-minutes] [role=option]')];

            return minutes[0].getAttribute('aria-disabled') === null &&
                minutes.slice(1).every((option) => option.getAttribute('aria-disabled') === 'true');
        })()");
});

it('closes without applying a pending hour on Escape', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-single]');
        window.__timeBefore = root.querySelector('[data-ui-bound=time]').textContent.trim();
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->keys('[data-ui-demo=time-picker-single] [data-ui-hours]', ['ArrowDown', 'Enter', 'Escape'])
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');

            return root.querySelector('[data-ui-popover]').matches(':popover-open') === false &&
                root.querySelector('[data-ui-bound=time]').textContent.trim() === window.__timeBefore;
        })()");
});

it('clears the value without opening the popover', function () {
    visit('/ui/time-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            root.querySelector('[aria-label=\"Kosongkan waktu\"]').click();

            return true;
        })()")
        ->wait(0.4)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');

            return root.querySelector('[data-ui-bound=time]').textContent.trim() === '' &&
                root.querySelector('[data-ui-trigger]').value === '' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('navigates with arrows and Tabs between the columns', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=time-picker-single] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->keys('[data-ui-demo=time-picker-single] [data-ui-hours]', ['ArrowDown', 'Enter', 'Tab'])
        ->wait(0.3)
        ->assertScript("document.activeElement.hasAttribute('data-ui-minutes')")
        ->keys('[data-ui-demo=time-picker-single] [data-ui-minutes]', ['ArrowDown', 'Enter'])
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');

            return root.querySelector('[data-ui-bound=time]').textContent.trim() === '10:30' &&
                root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('traps Tab focus inside the popover', function () {
    visit('/ui/time-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
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

it('keeps an off-step value only for its own hour', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-off-step]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step]');
            const popover = root.querySelector('[data-ui-popover]');

            return popover.querySelector('[data-ui-minutes] [data-minute=\"17\"]') !== null &&
                popover.querySelector('[data-ui-minutes] [data-minute=\"17\"]').getAttribute('aria-selected') === 'true';
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step]');
            root.querySelector('[data-ui-popover] [data-ui-hours] [data-hour=\"10\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step]');
            const popover = root.querySelector('[data-ui-popover]');
            const minutes = [...popover.querySelectorAll('[data-ui-minutes] [data-minute]')].map((option) => option.dataset.minute);

            return ! minutes.includes('17') && minutes.join('|') === '0|30';
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step]');
            root.querySelector('[data-ui-popover] [data-ui-minutes] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step]');
            const picker = window.Alpine.\$data(root.querySelector('[data-ui-time-picker]'));

            return picker.selected === '10:00';
        })()");
});

it('snaps an off-step open-to minute for new multiple selections', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-off-step-multiple]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-multiple]');
            const popover = root.querySelector('[data-ui-popover]');

            return popover.querySelector('[data-ui-minutes] [data-minute=\"17\"]') === null;
        })()")
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-multiple]');
            root.querySelector('[data-ui-popover] [data-ui-hours] [data-hour=\"10\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-multiple]');
            root.querySelector('[data-ui-popover] [data-ui-minutes] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-multiple]');
            root.querySelector('[data-ui-popover] [data-ui-add]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-multiple]');
            const picker = window.Alpine.\$data(root.querySelector('[data-ui-time-picker]'));

            return picker.selectedValues.includes('10:00');
        })()");
});

it('keeps focus inside when removing a selected time with the keyboard', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-multiple]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->keys('[data-ui-demo=time-picker-multiple] [data-ui-selected]', ['End', 'Enter'])
        ->wait(0.5)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-multiple]');

            return root.querySelector('[data-ui-bound=times]').textContent.trim() === '09:00' &&
                document.activeElement.hasAttribute('data-ui-selected') &&
                root.querySelector('[data-ui-popover]').contains(document.activeElement);
        })()");
});

it('supports Home, End, PageUp and PageDown in the hour column', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        document.querySelector('[data-ui-demo=time-picker-single] [data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->keys('[data-ui-demo=time-picker-single] [data-ui-hours]', ['End'])
        ->assertScript("document.activeElement.getAttribute('aria-activedescendant').endsWith('-18')")
        ->keys('[data-ui-demo=time-picker-single] [data-ui-hours]', ['Home'])
        ->assertScript("document.activeElement.getAttribute('aria-activedescendant').endsWith('-8')")
        ->keys('[data-ui-demo=time-picker-single] [data-ui-hours]', ['PageDown'])
        ->assertScript("document.activeElement.getAttribute('aria-activedescendant').endsWith('-13')")
        ->keys('[data-ui-demo=time-picker-single] [data-ui-hours]', ['PageUp'])
        ->assertScript("document.activeElement.getAttribute('aria-activedescendant').endsWith('-8')");
});

it('does not enable an hour using another hour off-step minute', function () {
    visit('/ui/time-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-bounds]');
            root.querySelector('[data-ui-trigger]').click();

            return true;
        })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-off-step-bounds]');
            const popover = root.querySelector('[data-ui-popover]');
            const hour = (value) => popover.querySelector('[data-ui-hours] [data-hour=\"' + value + '\"]');

            return hour('9').getAttribute('aria-disabled') === 'true' &&
                hour('10').getAttribute('aria-disabled') === 'true' &&
                hour('11').getAttribute('aria-disabled') === 'true';
        })()");
});

it('writes plain-form hidden fields for single and multiple', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-plain]');

        return root.querySelector('input[name=plain_time]').value === '14:30' &&
            [...root.querySelectorAll('input[name=\"plain_times[]\"]')].map((input) => input.value).join('|') === '09:00|16:00';
    })()");

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-plain]');
        const picker = root.querySelectorAll('[data-ui-time-picker]')[0];

        picker.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-plain]');
            const picker = root.querySelectorAll('[data-ui-time-picker]')[0];
            const popover = picker.querySelector('[data-ui-popover]');

            popover.querySelector('[data-ui-hours] [data-hour=\"8\"]').click();

            return true;
        })()")
        ->wait(0.2)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-plain]');
            const picker = root.querySelectorAll('[data-ui-time-picker]')[0];

            picker.querySelector('[data-ui-popover] [data-ui-minutes] [data-minute=\"0\"]').click();

            return true;
        })()")
        ->wait(0.6)
        ->assertScript("document.querySelector('[data-ui-demo=time-picker-plain] input[name=plain_time]').value === '08:00'");
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/time-picker')->on()->desktop();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-single]');
        window.__timeBefore = root.querySelector('[data-ui-trigger]').value;

        return true;
    })()")
        ->click('[data-ui-test=move-time]')
        ->wait(0.7)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            const trigger = root.querySelector('[data-ui-trigger]');

            return root.querySelector('[data-ui-bound=time]').textContent.trim() === '15:30' &&
                trigger.value !== window.__timeBefore;
        })()");
});

it('does not open when the picker is disabled', function () {
    visit('/ui/time-picker')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-disabled]');
            const trigger = root.querySelector('[data-ui-trigger]');

            trigger.click();

            return trigger.disabled && root.querySelector('[data-ui-popover]').matches(':popover-open') === false;
        })()");
});

it('keeps touch targets and the popover within the mobile viewport', function () {
    $page = visit('/ui/time-picker')->on()->mobile();

    $page->assertScript("(() => {
        const root = document.querySelector('[data-ui-demo=time-picker-single]');
        root.querySelector('[data-ui-trigger]').click();

        return true;
    })()")
        ->wait(0.3)
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=time-picker-single]');
            const popover = root.querySelector('[data-ui-popover]');
            const trigger = root.querySelector('[data-ui-trigger]');
            const hour = popover.querySelector('[data-ui-hours] [role=option]');
            const minute = popover.querySelector('[data-ui-minutes] [role=option]');
            const rect = popover.getBoundingClientRect();

            return trigger.getBoundingClientRect().height >= 44 &&
                hour.getBoundingClientRect().height >= 44 &&
                minute.getBoundingClientRect().height >= 44 &&
                Math.round(rect.left) === 8 &&
                Math.round(rect.width) === window.innerWidth - 16 &&
                rect.right <= window.innerWidth;
        })()");
});
