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
        ->click('[data-ui-demo="select-single"] [data-ui-option][data-value="design"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'Design services'")
        ->assertScript("document.querySelector('[data-ui-bound=industry]').textContent.trim() === 'design'");
});

it('filters with typeahead and selects with the keyboard', function () {
    $page = visit('/ui/select')->on()->desktop();
    $trigger = '[data-ui-demo="select-single"] [role="combobox"]';

    $page->click($trigger)
        ->keys($trigger, ['d', 'e', 's'])
        ->wait(0.2)
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-single] [data-ui-option]:not([hidden])').length === 1")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option]:not([hidden])').dataset.value === 'design'")
        ->keys($trigger, 'Enter')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'Design services'")
        ->assertScript("document.querySelector('[data-ui-bound=industry]').textContent.trim() === 'design'");
});

it('keeps multiple selections open and clears them', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('[data-ui-demo="select-multiple"] [role="combobox"]')
        ->click('[data-ui-demo="select-multiple"] [data-ui-option][data-value="design"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-multiple] [role=combobox]').getAttribute('aria-expanded') === 'true'")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-multiple] [data-ui-option][data-selected]').length === 2")
        ->assertScript("document.querySelector('[data-ui-bound=industries]').textContent.trim() === 'web, design'")
        ->click('[data-ui-demo="select-multiple"] button[aria-label="Kosongkan pilihan"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-bound=industries]').textContent.trim() === ''")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-multiple] [data-ui-option][data-selected]').length === 0");
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
        ->click('[data-ui-demo="select-pillbox"] button[aria-label="Hapus pilihan Marketing"]')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-bound=tags]').textContent.trim() === 'design'")
        ->assertScript("document.querySelectorAll('[data-ui-demo=select-pillbox] span[x-text]').length === 1");
});

it('reflects a server-side model update', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->click('Set legal')
        ->wait(0.8)
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [role=combobox]').value === 'Legal services'")
        ->assertScript("document.querySelector('[data-ui-demo=select-single] [data-ui-option][data-value=legal]').hasAttribute('data-selected')");
});

it('renders disabled and invalid states', function () {
    $page = visit('/ui/select')->on()->desktop();

    $page->assertScript("document.querySelector('[data-ui-demo=select-disabled] [role=combobox]').disabled === true")
        ->assertScript("document.querySelector('[data-ui-demo=select-invalid] [data-ui-select]').hasAttribute('data-invalid')")
        ->assertScript("document.querySelector('[data-ui-demo=select-invalid] [role=combobox]').getAttribute('aria-invalid') === 'true'");
});
