<?php

declare(strict_types=1);

it('has no serious accessibility issues on the select gallery', function (string $device) {
    visit('/ui/select')
        ->on()
        ->{$device}()
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
})->with(['desktop', 'mobile']);

it('selects an option with the mouse and updates the model', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('[data-ui-demo="select-single"] [role="combobox"]')
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').getAttribute('aria-expanded') === 'true'")
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=select-single] [data-ui-option]')).userSelect === 'none'")
        ->click('[data-ui-demo="select-single"] [data-ui-option][data-value="design"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'Design services'")
        ->assertScript("document.querySelector('[data-ui-bound=industry]').textContent.trim() === 'design'")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=design]').hasAttribute('data-active')")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=design]').hasAttribute('data-selected')")
        ->assertScript("!document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=photography]').hasAttribute('data-active')");
});

it('filters with typeahead and selects with the keyboard', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-single"] [role="combobox"]';

    $page->click($trigger)
        ->keys($trigger, ['d', 'e', 's'])
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'des'")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-single] [data-ui-option]:not([hidden])').length === 1")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option]:not([hidden])').dataset.value === 'design'")
        ->keys($trigger, 'Enter')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'Design services'")
        ->assertScript("document.querySelector('[data-ui-bound=industry]').textContent.trim() === 'design'")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=design]').hasAttribute('data-active')")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=design]').hasAttribute('data-selected')")
        ->assertScript("!document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=photography]').hasAttribute('data-active')");
});

it('keeps the typeahead character when typing reopens the listbox', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-single"] [role="combobox"]';

    $page->click($trigger)
        ->keys($trigger, 'Escape')
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').getAttribute('aria-expanded') === 'false'")
        ->keys($trigger, 'd')
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').getAttribute('aria-expanded') === 'true'")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'd'")
        ->keys($trigger, ['e', 's'])
        ->wait(0.2)
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-single] [data-ui-option]:not([hidden])').length === 1")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option]:not([hidden])').dataset.value === 'design'");
});

it('restores the initial selection when the form resets', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('[data-ui-demo="select-form-reset"] [role="combobox"]')
        ->click('[data-ui-demo="select-form-reset"] [data-ui-option][data-value="design"]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=select-form-reset] [role=combobox]').value === 'Design services'")
        ->assertScript("document.querySelector('[data-ui-demo=select-form-reset] input[type=hidden]').value === 'design'")
        ->click('[data-ui-demo="select-form-reset"] [data-ui-test=reset-select]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=select-form-reset] [role=combobox]').value === 'Web development'")
        ->assertScript("document.querySelector('[data-ui-demo=select-form-reset] input[type=hidden]').value === 'web'");
});

it('keeps multiple selections open and clears them', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('[data-ui-demo="select-multiple"] [role="combobox"]')
        ->click('[data-ui-demo="select-multiple"] [data-ui-option][data-value="design"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [role=combobox]').getAttribute('aria-expanded') === 'true'")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-multiple] [data-ui-option][data-selected]').length === 2")
        ->assertScript("document.querySelector('[data-ui-bound=industries]').textContent.trim() === 'web, design'")
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [data-ui-option][data-value=design]').hasAttribute('data-active')")
        ->assertScript("!document.querySelector('[data-ui-demo=select-multiple] [data-ui-option][data-value=photography]').hasAttribute('data-active')")
        ->click('[data-ui-demo="select-multiple"] button[aria-label="Kosongkan pilihan"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-bound=industries]').textContent.trim() === ''")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-multiple] [data-ui-option][data-selected]').length === 0")
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=select-multiple] button[aria-label=\"Kosongkan pilihan\"]')).display === 'none'");
});

it('adds and removes pills in the pillbox variant', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-pillbox"] [role="combobox"]';

    $page->click($trigger)
        ->click('[data-ui-demo="select-pillbox"] [data-ui-option][data-value="marketing"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-pillbox] [role=combobox]').getAttribute('aria-expanded') === 'true'")
        ->assertScript("document.querySelector('[data-ui-demo=select-pillbox]').textContent.includes('Marketing')")
        ->assertScript("document.querySelector('[data-ui-bound=tags]').textContent.trim() === 'design, marketing'")
        ->assertScript("document.querySelector('[data-ui-demo=select-pillbox] [data-ui-option][data-value=marketing]').hasAttribute('data-active')")
        ->click('[data-ui-demo="select-pillbox"] button[aria-label="Hapus pilihan Marketing"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-bound=tags]').textContent.trim() === 'design'")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-pillbox] button[aria-label^=\"Hapus pilihan\"]').length === 1");
});

it('keeps the active option on the keyboard selection in multiple mode', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-multiple"] [role="combobox"]';

    $page->click($trigger)
        ->keys($trigger, ['ArrowDown', 'Enter'])
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [data-ui-option][data-value=it]').hasAttribute('data-active')")
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [data-ui-option][data-value=it]').hasAttribute('data-selected')")
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [role=combobox]').getAttribute('aria-expanded') === 'true'");
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('Set legal')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'Legal services'")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=legal]').hasAttribute('data-selected')");
});

it('closes on a mouse selection and on an outside click', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('[data-ui-demo="select-single"] [role="combobox"]')
        ->click('[data-ui-demo="select-single"] [data-ui-option][data-value="design"]')
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').getAttribute('aria-expanded') === 'false'")
        ->click('[data-ui-demo="select-single"] [role="combobox"]')
        ->wait(0.2)
        ->click('[data-ui-outside]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').getAttribute('aria-expanded') === 'false'");
});

it('keeps multiple selection open until an outside click', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('[data-ui-demo="select-multiple"] [role="combobox"]')
        ->click('[data-ui-demo="select-multiple"] [data-ui-option][data-value="design"]')
        ->wait(0.4)
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [role=combobox]').getAttribute('aria-expanded') === 'true'")
        ->click('[data-ui-outside]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [role=combobox]').getAttribute('aria-expanded') === 'false'");
});

it('opens above the trigger and stays anchored while filtering', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-single"] [role="combobox"]';

    $page->assertScript(<<<'JS'
        (() => {
            document.body.style.paddingTop = '900px';
            const trigger = document.querySelector('[data-ui-demo=select-single] [role=combobox]');

            window.scrollTo(0, trigger.getBoundingClientRect().top + window.scrollY - (window.innerHeight - 120));

            return true;
        })()
        JS)
        ->click($trigger)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=select-single] [role=combobox]');
                const popover = trigger.closest('[data-ui-select]').querySelector('[data-ui-popover]');
                const triggerRect = trigger.getBoundingClientRect();
                const popoverRect = popover.getBoundingClientRect();

                return popover.matches(':popover-open') && popoverRect.bottom <= triggerRect.top + 1;
            })()
            JS)
        ->keys($trigger, ['d', 'e', 's'])
        ->wait(0.5)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=select-single] [role=combobox]');
                const popover = trigger.closest('[data-ui-select]').querySelector('[data-ui-popover]');
                const triggerRect = trigger.getBoundingClientRect();
                const popoverRect = popover.getBoundingClientRect();

                return Math.abs(triggerRect.top - popoverRect.bottom) <= 6 && popoverRect.height < 100;
            })()
            JS)
        ->assertScript(<<<'JS'
            (() => {
                document.body.style.paddingTop = '0';
                window.scrollTo(0, 0);
                window.dispatchEvent(new Event('resize'));

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=select-single] [role=combobox]');
                const popover = trigger.closest('[data-ui-select]').querySelector('[data-ui-popover]');
                const triggerRect = trigger.getBoundingClientRect();
                const popoverRect = popover.getBoundingClientRect();

                return popoverRect.top >= triggerRect.bottom - 1;
            })()
            JS);
});

it('uses a custom empty message and hides the listbox', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-single"] [role="combobox"]';

    $page->click($trigger)
        ->keys($trigger, ['z', 'z', 'z'])
        ->wait(0.5)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=status]').textContent.trim() === 'No industries found'")
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=select-single] [role=listbox]')).display === 'none'");
});

it('does not expose clear actions when disabled', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->assertScript("document.querySelector('[data-ui-demo=select-disabled] [role=combobox]').disabled === true")
        ->assertScript("getComputedStyle(document.querySelector('[data-ui-demo=select-disabled] button[aria-label=\"Kosongkan pilihan\"]')).display === 'none'");
});

it('skips disabled options during keyboard navigation', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-disabled-options"] [role="combobox"]';

    $page->click($trigger)
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=select-disabled-options] [data-ui-option][data-value=available]').hasAttribute('data-active')")
        ->assertScript("!document.querySelector('[data-ui-demo=select-disabled-options] [data-ui-option][data-value=blocked]').hasAttribute('data-active')")
        ->keys($trigger, ['ArrowDown', 'ArrowUp'])
        ->wait(0.2)
        ->assertScript("document.querySelector('[data-ui-demo=select-disabled-options] [data-ui-option][data-value=available]').hasAttribute('data-active')");
});

it('dispatches open, close, change, and clear events', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-single"] [role="combobox"]';

    $page->assertScript(<<<'JS'
        (() => {
            window.__uiEvents = [];
            const element = document.querySelector('[data-ui-demo=select-single] [role=combobox]');

            ['open', 'close', 'change', 'clear'].forEach((name) => element.addEventListener(name, () => window.__uiEvents.push(name)));

            return true;
        })()
        JS)
        ->click($trigger)
        ->wait(0.2)
        ->click('[data-ui-demo="select-single"] [data-ui-option][data-value="design"]')
        ->wait(0.4)
        ->click($trigger)
        ->wait(0.2)
        ->click('[data-ui-demo="select-single"] button[aria-label="Kosongkan pilihan"]')
        ->wait(0.4)
        ->assertScript("['open', 'close', 'change', 'clear'].every((name) => window.__uiEvents.includes(name))");
});

it('serializes plain form values', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->assertScript("document.querySelector('[data-ui-demo=select-plain] input[name=plain_industry]').value === 'design'")
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=select-plain] input[name^=plain_tags]')).map((input) => input.value).join('|') === 'design'")
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelectorAll('[data-ui-demo=select-plain] [data-ui-select]')[1];
                root.querySelector('[role=combobox]').focus();
                root.querySelector('[data-ui-option][data-value=marketing]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript("Array.from(document.querySelectorAll('[data-ui-demo=select-plain] input[name^=plain_tags]')).map((input) => input.value).sort().join('|') === 'design|marketing'");
});

it('uses touch-sized option rows on mobile', function () {
    $page = visit('/ui/select')->on()->mobile();

    $page->click('[data-ui-demo="select-single"] [role="combobox"]')
        ->wait(0.3)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option]').getBoundingClientRect().height >= 44");
});

it('keeps a wide popover inside the mobile viewport', function () {
    $page = visit('/ui/select')->on()->mobile();

    $page->click('[data-ui-demo="select-wide"] [role="combobox"]')
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const trigger = document.querySelector('[data-ui-demo=select-wide] [role=combobox]');
                const popover = trigger.closest('[data-ui-select]').querySelector('[data-ui-popover]');
                const rect = popover.getBoundingClientRect();

                return rect.left >= 8 && rect.right <= window.innerWidth - 8;
            })()
            JS);
});

it('centers option content on mobile', function () {
    visit('/ui/select')
        ->on()
        ->mobile()
        ->assertScript(<<<'JS'
            (() => {
                document.querySelector('[data-ui-demo=select-single] [role=combobox]').click();

                return true;
            })()
            JS)
        ->wait(0.3)
        ->assertScript(<<<'JS'
            (() => {
                const option = document.querySelector('[data-ui-demo=select-single] [data-ui-option]');
                const style = getComputedStyle(option);

                return style.alignItems === 'center' &&
                    Math.round(option.getBoundingClientRect().height) === 44;
            })()
            JS);
});

it('keeps pills compact and removes them from a pill click', function () {
    $page = visit('/ui/select')->on()->mobile();
    $pill = '[data-ui-demo=select-pillbox] [data-ui-pill][data-value=design]';

    $page->assertScript(<<<'JS'
        (() => {
            const root = document.querySelector('[data-ui-demo=select-pillbox]');
            const pill = root.querySelector('[data-ui-pill][data-value=design]');
            const button = pill.querySelector('button');
            const clear = root.querySelector('button[aria-label="Kosongkan pilihan"]');
            const anchor = root.querySelector('[role=combobox]').closest('[data-flux-control]');

            return !button.disabled &&
                clear !== null &&
                getComputedStyle(clear).display !== 'none' &&
                pill.getBoundingClientRect().height < 36 &&
                anchor.getBoundingClientRect().height >= 44;
        })()
        JS)
        ->click($pill)
        ->wait(0.8)
        ->assertScript(<<<'JS'
            (() => {
                const root = document.querySelector('[data-ui-demo=select-pillbox]');

                return root.querySelectorAll('[data-ui-pill]').length === 0 &&
                    getComputedStyle(root.querySelector('button[aria-label="Kosongkan pilihan"]')).display === 'none' &&
                    document.querySelector('[data-ui-bound=tags]').textContent.trim() === '';
            })()
            JS);
});

it('only offers creation when the query matches no options', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create] [role=combobox]';
    $create = '[data-ui-demo=select-create] [data-ui-create]';
    $empty = '[data-ui-demo=select-create] [role=status]';

    $page->click($trigger)
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display === 'none'")
        ->keys($trigger, ['b', 'r', 'a', 'n', 'd'])
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display === 'none'")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-create] [data-ui-option]').length === 1")
        ->assertScript("document.querySelector('[data-ui-bound=project-search]').textContent.trim() === 'brand'")
        ->keys($trigger, ['Escape'])
        ->click($trigger)
        ->wait(0.8)
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-create] [data-ui-option]').length === 3")
        ->keys($trigger, ['z'])
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display === 'none'")
        ->assertScript("getComputedStyle(document.querySelector('{$empty}')).display !== 'none'")
        ->keys($trigger, ['z'])
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display !== 'none'")
        ->assertScript("getComputedStyle(document.querySelector('{$empty}')).display === 'none'")
        ->assertScript("document.querySelector('[data-ui-bound=project-search]').textContent.trim() === 'zz'")
        ->keys($trigger, ['Enter'])
        ->wait(1)
        ->assertScript("document.querySelector('[data-ui-demo=select-create] [data-ui-option][data-value=zz]') !== null")
        ->assertScript("document.querySelector('[data-ui-bound=project]').textContent.trim() === 'zz'")
        ->assertNoJavaScriptErrors();
});

it('creates from the pillbox with the same event', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create-pillbox] [role=combobox]';
    $create = '[data-ui-demo=select-create-pillbox] [data-ui-create]';

    $page->click($trigger)
        ->keys($trigger, ['o', 'p', 's'])
        ->wait(0.3)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display !== 'none'")
        ->click($create)
        ->wait(1)
        ->assertScript("document.querySelector('[data-ui-demo=select-create-pillbox] [data-ui-pill][data-value=ops]') !== null")
        ->assertScript("document.querySelector('[data-ui-bound=tags]').textContent.includes('ops')")
        ->assertNoJavaScriptErrors();
});

it('trims the displayed create label', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create] [role=combobox]';
    $create = '[data-ui-demo=select-create] [data-ui-create]';

    $page->click($trigger)
        ->keys($trigger, [' ', ' ', 'z', 'z', ' ', ' '])
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display === 'flex'")
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).columnGap === 'normal'")
        ->assertScript("document.querySelector('{$create}').textContent.trim() === 'Create \"zz\"'")
        ->assertNoJavaScriptErrors();
});

it('filters pillbox options on the server without dropping pills', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create-pillbox] [role=combobox]';

    $page->click($trigger)
        ->keys($trigger, ['d', 'e', 'v'])
        ->wait(0.8)
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-create-pillbox] [data-ui-option]').length === 1")
        ->assertScript("document.querySelector('[data-ui-demo=select-create-pillbox] [data-ui-pill][data-value=design]') !== null")
        ->keys($trigger, 'Enter')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-create-pillbox] [data-ui-pill][data-value=development]') !== null")
        ->assertScript("document.querySelector('[data-ui-bound=tags]').textContent.includes('development')")
        ->wait(0.8)
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-create-pillbox] [data-ui-option]').length === 2")
        ->assertNoJavaScriptErrors();
});

it('creates a project from the modal flow', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create-modal] [role=combobox]';
    $create = '[data-ui-demo=select-create-modal] [data-ui-create]';

    $page->click($trigger)
        ->keys($trigger, ['o', 'p', 's'])
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display !== 'none'")
        ->click($create)
        ->wait(0.5)
        ->assertScript("document.querySelector('dialog[open]') !== null")
        ->assertScript("document.querySelector('dialog[open] input').value === 'ops'")
        ->click('dialog[open] button[type=submit]')
        ->wait(1)
        ->assertScript("document.querySelector('dialog[open]') === null")
        ->assertScript("document.querySelector('[data-ui-demo=select-create-modal] [data-ui-option][data-value=ops]') !== null")
        ->assertScript("document.querySelector('[data-ui-bound=modal-project]').textContent.trim() === 'ops'")
        ->assertNoJavaScriptErrors();
});

it('hides the create row while a backend search is pending', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create] [role=combobox]';
    $create = '[data-ui-demo=select-create] [data-ui-create]';

    $page->click($trigger)
        ->keys($trigger, ['z', 'z', 'z', 'z'])
        ->wait(0.8)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display !== 'none'")
        ->keys($trigger, ['Escape'])
        ->click($trigger)
        ->keys($trigger, ['a', 'n', 'a'])
        ->wait(0.1)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display === 'none'")
        ->wait(1)
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-create] [data-ui-option]').length === 1")
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display === 'none'")
        ->assertNoJavaScriptErrors();
});

it('creates a distinct option when the slug would collide', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create] [role=combobox]';
    $create = '[data-ui-demo=select-create] [data-ui-create]';

    $page->click($trigger)
        ->keys($trigger, ['B', 'r', 'a', 'n', 'd', 'i', 'n', 'g', '!'])
        ->wait(1)
        ->assertScript("getComputedStyle(document.querySelector('{$create}')).display !== 'none'")
        ->keys($trigger, ['Enter'])
        ->wait(1)
        ->assertScript("document.querySelector('[data-ui-demo=select-create] [data-ui-option][data-value=branding-2]') !== null")
        ->assertScript("document.querySelector('[data-ui-bound=project]').textContent.trim() === 'branding-2'")
        ->assertNoJavaScriptErrors();
});

it('moves focus to the retry action on Tab after a search failure', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo=select-create] [role=combobox]';
    $root = '[data-ui-demo=select-create] [data-ui-select]';
    $error = '[data-ui-demo=select-create] [data-ui-search-error]';
    $retry = '[data-ui-demo=select-create] [data-ui-retry]';

    $page->click($trigger)
        ->wait(1.2)
        ->assertScript(<<<JS
            (() => {
                const data = window.Alpine.\$data(document.querySelector('{$root}'));
                data.searchError = true;
                data.lastSearchQuery = null;
                data.applyQuery();

                return true;
            })()
            JS)
        ->keys($trigger, ['Tab'])
        ->wait(0.2)
        ->assertScript("document.activeElement.hasAttribute('data-ui-retry')")
        ->keys($retry, 'Enter')
        ->wait(0.1)
        ->assertScript("getComputedStyle(document.querySelector('{$error}')).display === 'none'")
        ->wait(1)
        ->assertScript("getComputedStyle(document.querySelector('{$error}')).display === 'none'")
        ->assertNoJavaScriptErrors();
});
