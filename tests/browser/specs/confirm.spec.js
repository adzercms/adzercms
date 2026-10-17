const { test, expect } = require('@playwright/test');
const { WALK, crawl } = require('../crawl');

const ASKING = 'form[data-confirm] button[type="submit"], button[type="submit"][data-confirm]';

const intercepted = async (page, action) => {
    const sent = [];

    await page.route((url) => url.pathname === action, (route) => {
        if (route.request().method() !== 'POST') {
            return route.continue();
        }
        sent.push(route.request().url());

        return route.abort();
    });

    return sent;
};

test('[ACMS-TEST-0016][ACMS-TEST-0018][ACMS-UI-0026] an irreversible action waits for consent in the dialog', async ({ page }) => {
    test.setTimeout(900000);

    const asking = page.locator(ASKING).first();
    await crawl(page, '/admin', [], { ...WALK, stop: async () => await asking.count() > 0 });
    expect(await asking.count(), 'the admin walk meets no irreversible action').toBeGreaterThan(0);

    const { action, question } = await asking.evaluate((button) => ({
        action: new URL(button.form.action).pathname,
        question: button.dataset.confirm ?? button.form.dataset.confirm,
    }));
    const sent = await intercepted(page, action);
    const card = page.url();

    await asking.click();

    const dialog = page.locator('#confirm[open]');
    await expect(dialog, 'the question comes by the frame dialog').toBeVisible();
    await expect(dialog).toContainText(question);

    await dialog.locator('.modal-footer button:not([value="yes"])').click();
    await expect(dialog).toBeHidden();
    await expect(page, 'the refusal leaves the page untouched').toHaveURL(card);
    expect(sent, 'no request leaves without consent').toEqual([]);
});
