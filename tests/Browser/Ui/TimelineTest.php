<?php

declare(strict_types=1);

it('has no serious accessibility issues on the timeline gallery', function (string $device) {
    visit('/ui/timeline')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('connects vertical items without lines at the outer edges', function () {
    visit('/ui/timeline')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=timeline-vertical] [data-ui-timeline]');
            const items = [...root.children];
            const line = (item, direction) => item.querySelector('[data-ui-timeline-line=' + direction + ']');
            const transparent = (el) => {
                const color = getComputedStyle(el).backgroundColor;

                return color === 'transparent' || color === 'rgba(0, 0, 0, 0)';
            };

            const first = items[0];
            const middle = items[1];
            const last = items[items.length - 1];

            const consecutive = items.slice(0, -1).every((item, index) => {
                const bottom = line(item, 'bottom').getBoundingClientRect();
                const nextTop = line(items[index + 1], 'top').getBoundingClientRect();

                return Math.abs(bottom.bottom - nextTop.top) < 1;
            });

            return items.length === 3 &&
                transparent(line(first, 'top')) &&
                ! transparent(line(first, 'bottom')) &&
                ! transparent(line(middle, 'top')) &&
                ! transparent(line(middle, 'bottom')) &&
                ! transparent(line(last, 'top')) &&
                transparent(line(last, 'bottom')) &&
                consecutive;
        })()");
});

it('lays a horizontal timeline out in a single row', function () {
    visit('/ui/timeline')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=timeline-horizontal] [data-ui-timeline]');
            const items = [...root.children].map((item) => item.getBoundingClientRect());
            const rail = root.querySelector('[data-ui-timeline-rail]');

            return getComputedStyle(root).flexDirection === 'row' &&
                getComputedStyle(root).flexWrap === 'nowrap' &&
                getComputedStyle(root).overflowX === 'auto' &&
                root.tabIndex === 0 &&
                getComputedStyle(rail).flexDirection === 'row' &&
                items.every((rect) => Math.abs(rect.top - items[0].top) < 1) &&
                items.every((rect, index) => index === 0 || rect.left > items[index - 1].left);
        })()");
});

it('keeps a vertical timeline out of the keyboard tab order', function () {
    visit('/ui/timeline')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=timeline-vertical] [data-ui-timeline]');

            return root.tabIndex === -1 && ! root.hasAttribute('tabindex');
        })()");
});

it('aligns the indicator to the start, center and end of an item', function () {
    visit('/ui/timeline')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=timeline-vertical-aligned] [data-ui-timeline]');
            const offsets = [...root.children].map((item) => {
                const itemRect = item.getBoundingClientRect();
                const indicator = item.querySelector('[data-ui-timeline-indicator]').getBoundingClientRect();

                return {
                    top: Math.round(indicator.top - itemRect.top),
                    bottom: Math.round(itemRect.bottom - indicator.bottom),
                };
            });

            const [start, center, end] = offsets;

            return start.top === 0 &&
                Math.abs(center.top - center.bottom) <= 1 &&
                center.top > 0 &&
                end.bottom === 0 &&
                end.top > 0;
        })()");
});

it('trims the edge lines in horizontal mode', function () {
    visit('/ui/timeline')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const root = document.querySelector('[data-ui-demo=timeline-horizontal] [data-ui-timeline]');
            const items = [...root.children];
            const line = (item, direction) => item.querySelector('[data-ui-timeline-line=' + direction + ']');
            const transparent = (el) => {
                const color = getComputedStyle(el).backgroundColor;

                return color === 'transparent' || color === 'rgba(0, 0, 0, 0)';
            };

            const first = items[0];
            const middle = items[1];
            const last = items[items.length - 1];

            const consecutive = items.slice(0, -1).every((item, index) => {
                const bottom = line(item, 'bottom').getBoundingClientRect();
                const nextTop = line(items[index + 1], 'top').getBoundingClientRect();

                return Math.abs(bottom.right - nextTop.left) < 1;
            });

            return transparent(line(first, 'top')) &&
                ! transparent(line(middle, 'top')) &&
                ! transparent(line(middle, 'bottom')) &&
                transparent(line(last, 'bottom')) &&
                consecutive;
        })()");
});

it('lets the indicator class part override the default look', function () {
    visit('/ui/timeline')
        ->on()
        ->desktop()
        ->assertScript("(() => {
            const items = [...document.querySelectorAll('[data-ui-demo=timeline-bare] [data-ui-timeline-indicator]')];
            const defaultColor = getComputedStyle(items[0]).color;
            const custom = items[items.length - 1];
            const customRect = custom.getBoundingClientRect();
            const iconRect = custom.querySelector('svg').getBoundingClientRect();

            return items.length === 3 &&
                items.every((item) => item.dataset.variant === 'bare') &&
                getComputedStyle(custom).color !== defaultColor &&
                Math.round(customRect.width) === 32 &&
                Math.round(iconRect.width) === 32;
        })()");
});
