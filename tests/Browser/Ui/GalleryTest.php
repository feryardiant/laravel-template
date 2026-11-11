<?php

declare(strict_types=1);

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
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-trigger]');
                trigger.scrollIntoView({ block: 'center' });
                trigger.click();

                return true;
            })()
            JS)
        ->wait(0.5)
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-popover]');
                const trigger = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-trigger]');
                const rect = popover.getBoundingClientRect();
                const triggerRect = trigger.getBoundingClientRect();
                const gap = rect.top > triggerRect.bottom ? rect.top - triggerRect.bottom : triggerRect.top - rect.bottom;
                window.__popoverTop = Math.round(rect.top);

                return popover.matches(':popover-open') &&
                    Math.abs(gap - 4) <= 1 &&
                    rect.top >= 0 &&
                    rect.bottom <= window.innerHeight &&
                    rect.left >= 0 &&
                    rect.right <= window.innerWidth;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                window.scrollBy(0, 200);

                return true;
            })()
            JS)
        ->wait(0.5)
        ->assertScript(<<<'JS'
            (() => {
                const popover = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-popover]');
                const trigger = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-trigger]');
                const rect = popover.getBoundingClientRect();
                const triggerRect = trigger.getBoundingClientRect();
                const gap = rect.top > triggerRect.bottom ? rect.top - triggerRect.bottom : triggerRect.top - rect.bottom;

                return popover.matches(':popover-open') &&
                    Math.round(rect.top) !== window.__popoverTop &&
                    Math.abs(gap - 4) <= 1 &&
                    rect.top >= 0 &&
                    rect.bottom <= window.innerHeight &&
                    rect.left >= 0 &&
                    rect.right <= window.innerWidth;
            })()
            JS);
});

it('locks page scrolling while a dialog popover is open', function (string $device) {
    visit('/ui')
        ->on()
        ->{$device}()
        ->assertScript('(window.scrollTo(0, 600), window.scrollY === 600)')
        ->assertScript(<<<'JS'
            (() => {
                document.querySelector('[data-ui-demo=popover-scroll] [data-ui-date-picker] [data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript("document.body.style.position === 'fixed' && document.body.style.top === '-600px'")
        ->assertScript("document.documentElement.style.overflow === 'hidden'")
        ->assertScript('(() => { window.scrollBy(0, 200); return window.scrollY === 0; })()')
        ->assertScript(<<<'JS'
            (() => {
                document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

                return true;
            })()
            JS)
        ->wait(0.2)
        ->assertScript("document.body.style.position === '' && document.documentElement.style.overflow === ''")
        ->assertScript('window.scrollY === 600')
        ->assertScript("document.activeElement.matches('[data-ui-demo=popover-scroll] [data-ui-date-picker] [data-ui-trigger]')");
})->with(['desktop', 'mobile']);

it('restores scrolling after committing a date', function () {
    visit('/ui')
        ->on()
        ->desktop()
        ->assertScript('(window.scrollTo(0, 600), window.scrollY === 600)')
        ->assertScript(<<<'JS'
            (() => {
                document.querySelector('[data-ui-demo=popover-scroll] [data-ui-date-picker] [data-ui-trigger]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript("document.body.style.position === 'fixed'")
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=popover-scroll] [data-ui-date-picker]');
                const cell = [...root.querySelectorAll('[data-ui-popover] td[data-date]:not([data-outside]):not([aria-disabled=true])')][0];

                cell.querySelector('button').click();

                return true;
            })()
            JS)
        ->wait(0.5)
        ->assertScript("document.body.style.position === '' && window.scrollY === 600");
});
