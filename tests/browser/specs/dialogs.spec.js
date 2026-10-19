const { test, expect } = require('@playwright/test');

const TEXTS = '/admin/texts';
const ASKING = `${TEXTS}/asking`;
const LISTED = new RegExp(`${TEXTS}$`);

let start = '';

const depth = (page) => page.evaluate(() => history.state?.adzercmsDialog ?? 0);
const settled = (page, level) => expect.poll(() => depth(page), 'history depth follows the open dialogs').toBe(level);
const record = (page) => page.locator('#record');
const question = (page) => page.locator('#confirm');

const open = async (page) => {
    await page.locator('tr[data-record]').first().click();
    await expect(record(page)).toBeVisible();
    await settled(page, 1);
};

const ask = async (page) => {
    await record(page).evaluate((dialog, action) => dialog.querySelector('.modal-body').insertAdjacentHTML(
        'beforeend',
        `<form method="post" action="${action}" data-confirm="?" id="asking"><button type="submit">?</button></form>`,
    ), ASKING);
    await page.click('#asking button');
    await expect(question(page)).toBeVisible();
};

const leaves = async (page) => {
    await page.goBack();
    await expect(page, 'the next step back leaves the page').toHaveURL(start);
};

test.beforeEach(async ({ page }) => {
    await page.goto('/admin');
    start = page.url();
    await page.goto(TEXTS);
});

test('[ACMS-UI-0076] going back closes the dialogs above its entry and stays on the page', async ({ page }) => {
    await page.evaluate(() => history.replaceState({ htmx: true }, ''));
    await open(page);
    await ask(page);
    await settled(page, 2);
    const sent = [];
    page.on('request', (request) => sent.push(request.url()));

    await page.goBack();
    await expect(question(page)).toBeHidden();
    await expect(record(page)).toBeVisible();
    await settled(page, 1);

    await page.goBack();
    await expect(record(page)).toBeHidden();
    await expect(page).toHaveURL(LISTED);
    await settled(page, 0);
    expect(sent, 'the htmx history restore does not see the step').toEqual([]);

    await leaves(page);
});

test('[ACMS-UI-0076] a dialog closed otherwise takes its entry back', async ({ page }) => {
    await open(page);

    await record(page).locator('.modal-close').click();
    await expect(record(page)).toBeHidden();
    await settled(page, 0);

    await leaves(page);
});

test('[ACMS-UI-0076] consent keeps its submission, the page back on the dialog entry returns to the bare one', async ({ page }) => {
    const list = new URL(TEXTS, page.url()).href;
    await page.route((url) => url.pathname === ASKING, async (route) => route.fulfill({
        response: await route.fetch({ url: list, method: 'GET' }),
    }));
    await open(page);
    await ask(page);

    await question(page).locator('button[value="yes"]').click();
    await page.waitForURL(`**${ASKING}`);

    await page.goBack();
    await expect(page).toHaveURL(LISTED);
    await settled(page, 0);

    await leaves(page);
});

test('[ACMS-UI-0076] leaving from a dialog goes from the bare entry', async ({ page }) => {
    await page.route((url) => url.pathname.startsWith(`${TEXTS}/`), (route) => (route.request().method() === 'POST'
        ? route.fulfill({ status: 204, headers: { 'HX-Redirect': TEXTS } })
        : route.continue()));
    await open(page);

    const loaded = page.waitForEvent('load');
    await record(page).locator('button[type="submit"]:not([name])').click();
    await loaded;
    await expect(record(page)).toBeHidden();
    await expect(page).toHaveURL(LISTED);

    await leaves(page);
});
