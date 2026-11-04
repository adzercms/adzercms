const { test, expect } = require('@playwright/test');

const PAGE = '/admin/references';

const FORM = `<form id="presets">
    <select name="provider">
        <option value="">-</option>
        <option value="1" data-sets="type=pppoe">One</option>
        <option value="2" data-sets="type=dhcp">Two</option>
        <option value="other">Other</option>
    </select>
    <label><input type="radio" name="type" value="pppoe">pppoe</label>
    <label><input type="radio" name="type" value="dhcp">dhcp</label>
    <div data-when="type=pppoe"><input name="login"></div>
    <div data-when="type=pppoe&provider=1"><input name="vlan"></div>
    <input name="provider_name">
    <a data-from="provider_name" data-url="https://search.test/?text={} support" href="https://search.test/?text= support">search</a>
</form>`;

const field = (page, selector) => page.locator(`#presets ${selector}`);

test.beforeEach(async ({ page }) => {
    await page.goto(PAGE);
    await page.locator('.panel-content').evaluate((content, html) => content.insertAdjacentHTML('afterbegin', html), FORM);
});

test('[ACMS-UI-0110] a choice option sets the field it names and the field stays editable', async ({ page }) => {
    await field(page, 'select[name="provider"]').selectOption('1');
    await expect(field(page, 'input[value="pppoe"]'), 'the option sets its field').toBeChecked();
    await expect(field(page, 'input[name="login"]'), 'the set value opens the fields of its condition').toBeVisible();

    await field(page, 'input[value="dhcp"]').check();
    await field(page, 'select[name="provider"]').selectOption('other');
    await expect(field(page, 'input[value="dhcp"]'), 'the set field stays editable, an option without a preset keeps it').toBeChecked();
    await expect(field(page, 'input[name="login"]')).toBeHidden();
});

test('[ACMS-UI-0027] a field on several conditions shows only while all of them hold', async ({ page }) => {
    await field(page, 'select[name="provider"]').selectOption('1');
    await expect(field(page, 'input[name="vlan"]'), 'all conditions hold').toBeVisible();

    await field(page, 'input[value="dhcp"]').check();
    await expect(field(page, 'input[name="vlan"]'), 'one condition fails').toBeHidden();
});

test('[ACMS-UI-0111] a link carries the value of the field it names', async ({ page }) => {
    await field(page, 'input[name="provider_name"]').fill('Local ISP');
    await expect(field(page, 'a[data-from]')).toHaveAttribute('href', 'https://search.test/?text=Local%20ISP support');
});
