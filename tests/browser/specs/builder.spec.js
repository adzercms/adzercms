const { test, expect } = require('@playwright/test');

const PAGES = '/admin/pages';
const READY = '[data-gjs-type="ready-block"]';

let faults = [];

const canvas = (page) => page.frameLocator('iframe.gjs-frame');

const saving = async (page) => {
    const save = await page.locator('[data-builder]').getAttribute('data-save');
    const sent = [];
    await page.route((url) => url.pathname === save, (route) => {
        const document = JSON.parse(/name="document"\r\n\r\n(.*)\r\n/.exec(route.request().postData())[1]);
        const blocks = document.blocks.map((block, at) => ({ id: block.id ?? 900 + at, anchor: `block-${block.id ?? 900 + at}` }));
        sent.push(document);
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'saved', version: 'saved', blocks }) });
    });

    return sent;
};

const carry = async (page, from, to, below = false) => {
    await from.hover();
    await page.mouse.down();
    const place = await to.boundingBox();
    const [x, y] = [place.x + place.width / 2, below ? place.y + place.height - 4 : place.y + place.height / 4];
    await page.mouse.move(x, y, { steps: 10 });
    await page.mouse.move(x, y + 1);

    return place;
};

const hold = (page, line) => expect.poll(
    () => page.locator('.gjs-placeholder').evaluateAll((nodes, top) => Math.min(...nodes.filter((node) => node.style.display === 'block').map((node) => Math.abs(node.getBoundingClientRect().top - top))), line),
    'the placeholder shows the place before the release',
).toBeLessThan(5);

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
    await page.locator('[data-layers] .gjs-layer-caret').first().click();
    await expect(page.locator('[data-layers] .gjs-layer-name:visible'), 'the layers are the page and its blocks').toHaveText([/./, ...blocks.map((block) => block.label)]);
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
    expect(sent[0], 'the whole document goes: the version it opened and the blocks it keeps in order with their values and style').toEqual({ version, blocks: blocks.slice(1).map((block) => ({ id: block.id, type: block.type, values: block.values, style: block.style })) });

    await page.click('[data-exit]');
    await expect(page, 'leaving after saving goes without a question').toHaveURL(/\/admin\/pages\/\d+$/);
});

test('[ACMS-BLD-0002][ACMS-FLOW-0022] a block moves by dragging its layer', async ({ page }) => {
    const blocks = JSON.parse(await page.locator('[data-builder]').getAttribute('data-blocks'));
    const layers = page.locator('[data-layers] .gjs-layer-name:visible');
    await page.locator('[data-layers] .gjs-layer-caret').first().click();
    await expect(layers).toHaveCount(blocks.length + 1);

    const to = await carry(page, page.locator('[data-layers] [data-toggle-move]:visible').last(), page.locator('[data-layers] .gjs-layer').nth(1));
    await hold(page, to.y);
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

test('[ACMS-BLD-0008][ACMS-FLOW-0022] the picture of a block comes from the panel of pictures: an upload goes first, a click puts it into the field', async ({ page }) => {
    const root = page.locator('[data-builder]');
    const blocks = JSON.parse(await root.getAttribute('data-blocks'));
    const at = blocks.findIndex((block) => block.type === 'hero');
    test.skip(at < 0, 'the page has no hero block');
    const target = blocks[at];
    const dialog = page.locator('#record');
    const panel = page.locator('[data-pictures-panel]');
    const refusal = panel.locator('[data-pictures-refusal]');
    const assets = panel.locator('.gjs-am-asset');
    const upload = (name, buffer) => panel.locator('input[type="file"]').setInputFiles({ name, mimeType: 'image/png', buffer });

    await canvas(page).locator(`#${target.anchor}`).click();
    await page.locator(`.gjs-toolbar-item[title="${await root.getAttribute('data-edit')}"]`).click();
    await expect(dialog).toBeVisible();
    await dialog.locator('[data-picture-pick]').click();
    await expect(panel, 'the panel opens over the dialog').toBeVisible();
    const listed = await assets.count();

    await upload('fake.png', Buffer.from('not a picture'));
    await expect(refusal, 'a file other than a photo is refused with its reason').toBeVisible();
    faults = faults.filter((fault) => !/status of 422|^\{"message":/.test(fault));
    await upload('fiber.png', Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64'));
    await expect(assets, 'the upload goes first').toHaveCount(listed + 1);
    await expect(refusal).toBeHidden();

    await assets.first().click();
    await expect(panel).toBeHidden();
    await expect(dialog, 'the choice goes back to the dialog').toBeVisible();
    const name = await dialog.locator('input[type="hidden"][name="value[picture]"]').inputValue();
    expect(name).toMatch(/^[0-9a-f]{32}\.webp$/);
    await expect(dialog.locator('[data-picture] .picture-preview img')).toBeVisible();
    await dialog.locator('form > button[type="submit"]').click();
    await expect(dialog).toBeHidden();
    await expect(canvas(page).locator(`#${target.anchor} img`), 'the canvas shows the chosen picture').toHaveAttribute('src', new RegExp(`${name}$`));

    const sent = await saving(page);
    await page.click('[data-save-document]');
    await expect(page.locator('[data-status]')).toHaveText('saved');
    expect(sent[0].blocks[at].values.picture, 'saving sends the name of the chosen picture').toBe(name);
});

test('[ACMS-BLD-0003][ACMS-FLOW-0022] a block of the panel takes its place by dragging after its dialog, saving numbers it', async ({ page }) => {
    const root = page.locator('[data-builder]');
    const blocks = JSON.parse(await root.getAttribute('data-blocks'));
    const kinds = JSON.parse(await root.getAttribute('data-types'));
    const kind = kinds.find((one) => one.type === 'text') ?? kinds[0];
    const dialog = page.locator('#record');
    const ready = canvas(page).locator(READY);
    const drag = async () => {
        const place = await carry(page, page.locator(`[data-palette] [data-type="${kind.type}"]`), ready.first());
        await hold(page, place.y);
        await page.mouse.up();
        await expect(dialog, 'a dropped block asks for its values').toBeVisible();
    };

    await drag();
    await dialog.locator('.modal-close').click();
    await expect(ready, 'a closed dialog places no block').toHaveCount(blocks.length);

    await drag();
    await dialog.locator('input[name="value[title]"]').fill('Fiber to every flat');
    await dialog.locator('form > button[type="submit"]').click();
    await expect(dialog).toBeHidden();
    await expect(ready).toHaveCount(blocks.length + 1);
    await expect(ready.first().locator('h1, h2').first(), 'the block takes the place it was dropped at').toHaveText('Fiber to every flat');

    const sent = await saving(page);
    await page.click('[data-save-document]');
    await expect(page.locator('[data-status]')).toHaveText('saved');
    expect(sent[0].blocks[0], 'saving sends the new block with its type').toMatchObject({ id: null, type: kind.type, values: { title: 'Fiber to every flat' } });
    await expect(ready.first(), 'the saved block takes its number').toHaveAttribute('id', 'block-900');

    await page.click('[data-undo]');
    await expect(ready, 'saving starts the history anew').toHaveCount(blocks.length + 1);
});

test('[ACMS-BLD-0006][ACMS-FLOW-0022] a block takes its style from the panel, the computer rules go for every width, a phone rule goes over them', async ({ page }) => {
    const root = page.locator('[data-builder]');
    const blocks = JSON.parse(await root.getAttribute('data-blocks'));
    const styled = blocks.findIndex((block) => block.style['']?.desktop);
    const block = canvas(page).locator(READY).first();
    const property = (name) => page.locator(`[data-style-panel] .gjs-sm-property__${name} input`);
    const set = async (sector, name, value) => {
        if (!(await property(name).isVisible())) {
            await page.locator(`[data-style-panel] .gjs-sm-sector__${sector} .gjs-sm-sector-title`).click();
        }
        await property(name).fill(value);
        await property(name).press('Enter');
    };

    if (styled >= 0) {
        const stored = canvas(page).locator(`#${blocks[styled].anchor}`);
        const [[name, value]] = Object.entries(blocks[styled].style[''].desktop);
        const declared = await stored.evaluate((node, [one, given]) => {
            const probe = node.ownerDocument.createElement(node.localName);
            probe.style.setProperty(one, given);
            node.parentElement.append(probe);
            const shown = getComputedStyle(probe).getPropertyValue(one);
            probe.remove();
            return shown;
        }, [name, value]);
        await expect(stored, 'the canvas shows the stored style of a block').toHaveCSS(name, declared);
    }

    await block.click();
    await set('text', 'color', '#112233');
    await expect(block, 'the canvas shows the style of the panel').toHaveCSS('color', 'rgb(17, 34, 51)');

    const before = await block.evaluate((node) => getComputedStyle(node).paddingTop);
    await page.click('[data-screen="phone"]');
    await set('spacing', 'padding-top', '48');
    await expect(block, 'a phone rule goes over the computer rules').toHaveCSS('padding-top', '48px');
    await expect(block).toHaveCSS('color', 'rgb(17, 34, 51)');
    await page.click('[data-screen="desktop"]');
    await expect(block, 'a phone rule stays on its width').toHaveCSS('padding-top', before);

    const sent = await saving(page);
    await page.click('[data-save-document]');
    await expect(page.locator('[data-status]')).toHaveText('saved');
    expect(sent[0].blocks[0].style, 'saving sends the rules of every width').toEqual({ ...blocks[0].style, '': { ...blocks[0].style[''], desktop: { ...blocks[0].style['']?.desktop, color: '#112233' }, phone: { ...blocks[0].style['']?.phone, 'padding-top': '48px' } } });
    if (styled > 0) {
        expect(sent[0].blocks[styled].style, 'saving keeps the style of other blocks').toEqual(blocks[styled].style);
    }
});

test('[ACMS-BLD-0004][ACMS-FLOW-0022] a free block takes its elements by dragging, its text changes in place, an element takes its link and style, saving sends the tree', async ({ page }) => {
    const root = page.locator('[data-builder]');
    const blocks = JSON.parse(await root.getAttribute('data-blocks'));
    const free = await root.getAttribute('data-free');
    test.skip(!JSON.parse(await root.getAttribute('data-types')).some((one) => one.type === free), 'the instance has no free block');
    const labels = Object.fromEntries(JSON.parse(await root.getAttribute('data-elements')).map(({ kind, label }) => [kind, label]));
    const ready = canvas(page).locator(READY);
    const body = canvas(page).locator('.site-free-body').first();
    const property = (name) => page.locator(`[data-style-panel] .gjs-sm-property__${name} input`);
    const drag = async (from, to, below = false) => {
        await carry(page, page.locator(`[data-palette] ${from}`), to, below);
        await expect.poll(() => page.locator('.gjs-placeholder').evaluateAll((nodes) => nodes.some((node) => node.style.display === 'block')), 'the placeholder shows the place before the release').toBe(true);
        await page.mouse.up();
    };

    const stored = blocks.findIndex((block) => block.type === free && block.values.elements);
    if (stored >= 0) {
        const tree = JSON.parse(blocks[stored].values.elements);
        await expect(canvas(page).locator(`#${blocks[stored].anchor} [data-element]`), 'the canvas shows the elements of a stored free block').toHaveCount(JSON.stringify(tree).match(/"key":/g).length);
        const sent = await saving(page);
        await page.click('[data-save-document]');
        await expect(page.locator('[data-status]')).toHaveText('saved');
        expect(JSON.parse(sent[0].blocks[stored].values.elements), 'an untouched free block goes back with its tree').toEqual(tree);
        expect(sent[0].blocks[stored].style).toEqual(blocks[stored].style);
    }

    await drag(`[data-type="${free}"]`, ready.first());
    await expect(ready, 'a free block takes its place without a dialog').toHaveCount(blocks.length + 1);
    await expect(page.locator('#record')).toBeHidden();
    await expect(ready.first()).toHaveClass(/site-free/);

    await drag('[data-element-kind="text"]', body);
    await expect(body.locator('[data-element]')).toHaveCount(1);
    await drag('[data-element-kind="button"]', body, true);
    await expect(body.locator('[data-element]'), 'the elements go into the free block').toHaveCount(2);

    const text = body.locator('.prose[data-element]');
    await text.dblclick();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.type('Fiber to every flat');
    await page.keyboard.press('ControlOrMeta+A');
    await page.locator('.gjs-rte-action').first().click();
    await expect(text.locator('b, strong'), 'the text takes the bold of the editor in place').toHaveText('Fiber to every flat');

    await body.locator('a.button').click();
    await page.locator('[data-traits-panel] input').first().fill('https://t.me/fiber');
    await page.locator('[data-traits-panel] input').first().press('Tab');
    await expect(body.locator('a.button')).toHaveAttribute('href', 'https://t.me/fiber');

    await text.click();
    if (!(await property('color').isVisible())) {
        await page.locator('[data-style-panel] .gjs-sm-sector__text .gjs-sm-sector-title').click();
    }
    await property('color').fill('#112233');
    await property('color').press('Enter');
    await expect(text, 'an element takes the style of the panel').toHaveCSS('color', 'rgb(17, 34, 51)');

    const sent = await saving(page);
    await page.click('[data-save-document]');
    await expect(page.locator('[data-status]')).toHaveText('saved');
    const [placed] = sent[0].blocks;
    expect(placed, 'saving sends the free block with the style of its elements by their keys').toMatchObject({ id: null, type: free, style: { e1: { desktop: { color: '#112233' } } } });
    expect(JSON.parse(placed.values.elements), 'saving sends the tree of the elements').toEqual([
        { kind: 'text', key: 'e1', paragraphs: [[{ text: 'Fiber to every flat', bold: true }]] },
        { kind: 'button', key: 'e2', label: labels.button, link: 'https://t.me/fiber' },
    ]);
});
