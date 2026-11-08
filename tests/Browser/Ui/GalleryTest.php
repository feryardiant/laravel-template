<?php

declare(strict_types=1);

it('links to every component page', function () {
    visit('/ui')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const hrefs = [...document.querySelectorAll('a')].map((link) => link.getAttribute('href') || '');
            const required = [
                '/ui/select',
                '/ui/autocomplete',
                '/ui/calendar',
                '/ui/date-picker',
                '/ui/time-picker',
                '/ui/datetime-picker',
                '/ui/file-upload',
            ];

            return required.every((path) => hrefs.some((href) => href.endsWith(path)));
        })()");
});

it('has no serious accessibility issues on the gallery', function (string $device) {
    visit('/ui')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('keeps an open popover inside the viewport while scrolling', function () {
    visit('/ui')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const trigger = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-trigger]');
            trigger.scrollIntoView({ block: 'center' });
            trigger.click();

            return true;
        })()")
        ->wait(0.5)
        ->assertScript("(() => {
            const popover = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-popover]');
            const rect = popover.getBoundingClientRect();
            window.__popoverTop = Math.round(rect.top);

            return popover.matches(':popover-open') &&
                rect.top >= 0 &&
                rect.bottom <= window.innerHeight &&
                rect.left >= 0 &&
                rect.right <= window.innerWidth;
        })()")
        ->assertScript('(() => {
            window.scrollBy(0, 200);

            return true;
        })()')
        ->wait(0.5)
        ->assertScript("(() => {
            const popover = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-popover]');
            const rect = popover.getBoundingClientRect();

            return popover.matches(':popover-open') &&
                Math.round(rect.top) !== window.__popoverTop &&
                rect.top >= 0 &&
                rect.bottom <= window.innerHeight &&
                rect.left >= 0 &&
                rect.right <= window.innerWidth;
        })()");
});
