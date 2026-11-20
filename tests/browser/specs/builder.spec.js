const { test, expect } = require('@playwright/test');

const PAGES = '/admin/pages';
const READY = '[data-gjs-type="ready-block"]';

let faults = [];

const canvas = (page) => page.frameLocator('iframe.gjs-frame');

const saving = async (page) => {
    const save = await page.locator('[data-builder]').getAttribute('data-save');
    const sent = [];
    await page.route((url) => url.pathname === save, (route) => {
        sent.push(JSON.parse(/name="document"\r\n\r\n(.*)\r\n/.exec(route.request().postData())[1]));
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'saved', version: 'saved' }) });
    });

    return sent;
};

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

test('[ACMS-BLD-0002][ACMS-FLOW-0022] a block leaves after a question, the document goes whole on saving, leaving with unsaved changes asks', async ({ page }) => {
    const root = page.locator('[data-builder]');
    const blocks = JSON.parse(await root.getAttribute('data-blocks'));
    const version = await root.getAttribute('data-version');
    const question = page.locator('#confirm');
    const drop = async (answer) => {
        await canvas(page).locator(READY).first().click();
        await page.locator('.gjs-toolbar-item').last().click();
        await expect(question).toBeVisible();
        await expect(question.locator('[data-confirm-text]')).toHaveText(blocks[0].question);
        await question.locator(answer).click();
    };

    await drop('button.secondary');
    await expect(canvas(page).locator(READY), 'a declined question keeps the block').toHaveCount(blocks.length);
    await drop('button.danger');
    await expect(canvas(page).locator(READY)).toHaveCount(blocks.length - 1);

    await page.click('[data-exit]');
    await expect(question, 'leaving with unsaved changes asks').toBeVisible();
    await question.locator('button.secondary').click();
    await expect(page).toHaveURL(/\/builder$/);

    const sent = await saving(page);
    await page.click('[data-save-document]');
    await expect(page.locator('[data-status]')).toHaveText('saved');
    expect(sent).toHaveLength(1);
    expect(sent[0], 'the whole document goes: the version it opened and the blocks it keeps in order with their values').toEqual({ version, blocks: blocks.slice(1).map((block) => ({ id: block.id, values: block.values })) });

    await page.click('[data-exit]');
    await expect(page, 'leaving after saving goes without a question').toHaveURL(/\/admin\/pages\/\d+$/);
});

test('[ACMS-BLD-0002][ACMS-FLOW-0022] a block moves by dragging its layer', async ({ page }) => {
    const blocks = JSON.parse(await page.locator('[data-builder]').getAttribute('data-blocks'));
    const layers = page.locator('[data-layers] .gjs-layer-name');
    await page.locator('[data-layers] .gjs-layer-caret').first().click();
    await expect(layers).toHaveCount(blocks.length + 1);

    const from = await page.locator('[data-layers] [data-toggle-move]').last().boundingBox();
    const to = await layers.nth(1).boundingBox();
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    await page.mouse.move(to.x + 10, to.y + 2, { steps: 10 });
    await page.mouse.up();

    const order = blocks.map((block) => block.anchor);
    await expect.poll(() => canvas(page).locator(READY).evaluateAll((nodes) => nodes.map((node) => node.id))).toEqual([order.at(-1), ...order.slice(0, -1)]);
});

test('[ACMS-BLD-0003][ACMS-FLOW-0022] a ready block takes its values from the dialog, the canvas shows the block the server draws, saving sends them', async ({ page }) => {
    const root = page.locator('[data-builder]');
    const blocks = JSON.parse(await root.getAttribute('data-blocks'));
    const at = blocks.findIndex((block) => block.dialog && block.values.title);
    test.skip(at < 0, 'the page has no ready block with a title');
    const target = blocks[at];
    const heading = canvas(page).locator(`#${target.anchor}`).locator('h1, h2').first();
    const dialog = page.locator('#record');

    await canvas(page).locator(`#${target.anchor}`).click();
    await page.locator(`.gjs-toolbar-item[title="${await root.getAttribute('data-edit')}"]`).click();
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('input[name="value[title]"]'), 'the dialog shows the values of the block').toHaveValue(target.values.title);
    await dialog.locator('input[name="value[title]"]').fill('Fiber to every flat');
    await dialog.locator('form > button[type="submit"]').click();
    await expect(dialog).toBeHidden();
    await expect(heading, 'the canvas shows the block with the new values').toHaveText('Fiber to every flat');

    await page.click('[data-undo]');
    await expect(heading, 'undo brings the values back').toHaveText(target.values.title);
    await page.click('[data-redo]');
    await expect(heading).toHaveText('Fiber to every flat');

    const sent = await saving(page);
    await page.click('[data-save-document]');
    await expect(page.locator('[data-status]')).toHaveText('saved');
    expect(sent[0].blocks[at].values.title, 'saving sends the values of the dialog').toBe('Fiber to every flat');
});
