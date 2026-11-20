const { test, expect } = require('@playwright/test');

const PAGES = '/admin/pages';
const READY = '[data-gjs-type="ready-block"]';

let faults = [];

const canvas = (page) => page.frameLocator('iframe.gjs-frame');

test.beforeEach(async ({ page, isMobile }) => {
    test.skip(isMobile, 'the editor works on a computer screen');
    faults = [];
    page.on('console', (message) => message.type() === 'error' && faults.push(message.text()));
    page.on('pageerror', (error) => faults.push(error.message));

    const listing = await page.goto(PAGES);
    test.skip(!listing?.ok(), 'the instance has no pages module');
    const record = await page.locator('tr[data-href]').first().getAttribute('data-href').catch(() => null);
    test.skip(!record, 'the instance has no page');
    await page.goto(record);
    const editor = await page.locator('.card-actions a[href$="/builder"]').getAttribute('href').catch(() => null);
    test.skip(!editor, 'the instance has no builder module');
    await page.goto(editor);
});

test.afterEach(() => {
    expect(faults, 'the editor runs without script errors').toEqual([]);
});

test('[ACMS-BLD-0001][ACMS-US-0077] the editor shows the page on the site frame with its blocks in order', async ({ page }) => {
    const blocks = JSON.parse(await page.locator('[data-builder]').getAttribute('data-blocks'));

    await expect(canvas(page).locator(READY)).toHaveCount(blocks.length);
    expect(await canvas(page).locator(READY).evaluateAll((nodes) => nodes.map((node) => node.id))).toEqual(blocks.map((block) => block.anchor));
    await expect(canvas(page).locator('html[data-theme]'), 'the canvas takes the theme of the site').toHaveCount(1);
    await expect(canvas(page).locator('.site-header'), 'the canvas carries the site frame').toBeVisible();
    await expect(canvas(page).locator('.site-header'), 'the canvas carries the styles of the site').toHaveCSS('position', 'sticky');
    await expect(page.locator('[data-layers] .gjs-layer-name'), 'the layers are the page and its blocks').toHaveText([/./, ...blocks.map((block) => block.label)]);
});

test('[ACMS-BLD-0001][ACMS-FLOW-0022] a screen width of the bar narrows the canvas to it', async ({ page }) => {
    const frame = page.locator('iframe.gjs-frame');
    const wide = (await frame.boundingBox()).width;

    await page.click('[data-screen="phone"]');
    await expect.poll(async () => (await frame.boundingBox()).width).toBeLessThan(wide);
    await expect(page.locator('[data-screen="phone"]')).toHaveAttribute('aria-pressed', 'true');

    await page.click('[data-screen="desktop"]');
    await expect.poll(async () => (await frame.boundingBox()).width).toBe(wide);
});
