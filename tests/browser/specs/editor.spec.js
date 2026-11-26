const { test, expect } = require('@playwright/test');
const { tap, publish } = require('../hub');

const held = async (page) => {
    const sent = [];
    await page.route('**/*', (route) => {
        const request = route.request();
        if (request.method() === 'GET') {
            return route.fallback();
        }
        sent.push(request.postDataBuffer()?.toString('utf8') ?? '');

        return route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>held</title>' });
    });

    return sent;
};

const body = (sent) => sent.match(/name="body"\r\n\r\n([\s\S]*?)\r\n--/)?.[1].replace(/\r\n/g, '\n');

const editor = (scope) => ({
    area: scope.locator('[data-editor] .tiptap'),
    source: scope.locator('[data-editor] textarea'),
    mark: (name) => scope.locator(`[data-editor-mark="${name}"]`),
    md: scope.locator('[data-editor-source]'),
});

const ticket = async (page, wanted) => {
    await page.goto('/support', { waitUntil: 'networkidle' });
    const rows = await page.locator('tbody tr[data-href^="/support/"]').evaluateAll((found) => found.map((row) => row.dataset.href));

    for (const row of rows) {
        await page.goto(row, { waitUntil: 'networkidle' });
        if (await page.locator(wanted).count()) {
            return row;
        }
    }

    return null;
};

test('[ACMS-US-0008][ACMS-UI-0190][ACMS-TEST-0018] the message takes the marks of the buttons, MD shows its markup and brings the edit back', async ({ page }) => {
    const sent = await held(page);
    await page.goto('/support/new', { waitUntil: 'networkidle' });
    const form = page.locator('form:has([data-editor])');
    const message = editor(form);
    await expect(message.area, 'the field is edited visually').toBeVisible();
    await expect(message.source).toBeHidden();

    await message.area.click();
    await page.keyboard.type('Bold words');
    await page.keyboard.press('ControlOrMeta+a');
    await message.mark('bold').click();
    await expect(message.area.locator('strong')).toHaveText('Bold words');
    await expect(message.mark('bold')).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('End');
    await page.keyboard.press('Enter');
    await message.mark('bold').click();
    await message.mark('numbers').click();
    await page.keyboard.type('first item');
    await expect(message.area.locator('ol li')).toHaveText('first item');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Enter');
    await page.keyboard.type('see https://example.com/page ');
    await expect(message.area.locator('a[href="https://example.com/page"]'), 'a typed address becomes a link').toHaveCount(1);

    await message.md.click();
    await expect(message.source, 'MD shows the markup').toBeVisible();
    await expect(message.area).toBeHidden();
    await expect(message.mark('bold'), 'the marks stand off over the markup').toBeDisabled();
    expect(await message.source.inputValue()).toMatch(/^\*\*Bold words\*\*\n\n1\. first item\n\nsee .*https:\/\/example\.com\/page/);

    await message.source.fill(`${await message.source.inputValue()}\n\n> quoted line`);
    await message.md.click();
    await expect(message.area.locator('blockquote'), 'the edited markup comes back visual').toHaveText('quoted line');
    await expect(message.mark('bold')).toBeEnabled();

    const markup = await message.source.inputValue();
    await page.locator('input[name="subject"]').fill('Editor');
    await form.locator(':scope > button[type="submit"]').click();
    await expect.poll(() => sent.length).toBe(1);
    expect(body(sent[0]), 'the form sends the markup').toBe(markup);
});

test('[ACMS-US-0008][ACMS-UI-0190][ACMS-SUP-0016] the picked help article lands in the visual reply', async ({ page }) => {
    await held(page);
    test.skip(await ticket(page, '.help-insert') === null, 'no open ticket with the help search');
    const reply = editor(page.locator('.chat-reply'));
    const article = page.locator('.help-results [data-help-insert]').first();
    await expect(article, 'the help search offers articles').toBeVisible();

    await reply.area.click();
    await page.keyboard.type('Read this');
    await article.click();
    const line = await article.getAttribute('data-help-insert');
    await expect(reply.area.locator('p').last(), 'the article line follows the typed text').toHaveText(line);
    expect(await reply.source.inputValue()).toMatch(/^Read this\n\n/);
});

test('[ACMS-US-0008][ACMS-UI-0190] the edit dialog of an operator message raises the editor when opened', async ({ page }) => {
    await held(page);
    test.skip(await ticket(page, 'dialog.modal [data-editor]') === null, 'no unread operator message in an open ticket');
    const dialog = page.locator('dialog.modal:has([data-editor])').first();
    const edit = editor(dialog);
    await expect(edit.area, 'a closed dialog holds the plain field').toHaveCount(0);

    await page.locator(`[data-modal="${await dialog.getAttribute('id')}"]`).click();
    await expect(edit.area).toBeVisible();
    await expect(edit.source).toBeHidden();
});

test('[ACMS-US-0008][ACMS-UI-0189][ACMS-UI-0190] a live reply block swapped by a hub event raises its editor', async ({ page }) => {
    await held(page);
    const hub = await tap(page);
    test.skip(await ticket(page, '#live-reply [data-editor]') === null, 'no open ticket');
    await expect.poll(() => hub.subscribed.length, { message: 'the page subscribes to the hub' }).toBeGreaterThan(0);
    await page.locator('#live-reply').evaluate((block) => { block.dataset.probe = 'old'; });

    publish(hub, 1);

    await expect(page.locator('#live-reply:not([data-probe])'), 'the event swaps the reply block').toHaveCount(1);
    await expect(page.locator('#live-reply .tiptap'), 'the swapped block raises its editor').toBeVisible();
    await expect(page.locator('#live-reply .editor-area')).toHaveCount(1);
    await expect(page.locator('#live-reply textarea[name="body"]')).toBeHidden();
});
