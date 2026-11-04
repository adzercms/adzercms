const { test, expect } = require('@playwright/test');
const { WALK, crawl } = require('../crawl');

const ASKING = 'form[data-confirm] button[type="submit"], button[type="submit"][data-confirm]';
const CHOOSING = 'form:has(option[data-confirm]:not(:checked))';

const met = async (page, locator, absent) => {
    await crawl(page, '/admin', [], { ...WALK, visit: async () => await locator.count() > 0 });
    expect(await locator.count(), absent).toBeGreaterThan(0);
};

const refused = async (page, action, question, submit) => {
    const sent = [];

    await page.route((url) => url.pathname === action, (route) => {
        if (route.request().method() !== 'POST') {
            return route.continue();
        }
        sent.push(route.request().url());

        return route.abort();
    });

    const card = page.url();

    await submit();

    const dialog = page.locator('#confirm[open]');
    await expect(dialog, 'the question comes by the frame dialog').toBeVisible();
    await expect(dialog).toContainText(question);

    await dialog.locator('.modal-footer button:not([value="yes"])').click();
    await expect(dialog).toBeHidden();
    await expect(page, 'the refusal leaves the page untouched').toHaveURL(card);
    expect(sent, 'no request leaves without consent').toEqual([]);
};

test('[ACMS-TEST-0016][ACMS-TEST-0018][ACMS-UI-0026] an irreversible action waits for consent in the dialog', async ({ page }) => {
    test.setTimeout(900000);

    const asking = page.locator(ASKING).first();
    await met(page, asking, 'the admin walk meets no irreversible action');

    const { action, question } = await asking.evaluate((button) => ({
        action: new URL(button.form.action).pathname,
        question: button.dataset.confirm ?? button.form.dataset.confirm,
    }));

    await refused(page, action, question, () => asking.click());
});

test('[ACMS-TEST-0016][ACMS-TEST-0018][ACMS-UI-0026] a list asks the question of its chosen option', async ({ page }) => {
    test.setTimeout(900000);

    const choosing = page.locator(CHOOSING).first();
    await met(page, choosing, 'the admin walk meets no list with a question');

    const option = choosing.locator('option[data-confirm]:not(:checked)').first();
    const { action, value, question } = await option.evaluate((chosen) => ({
        action: new URL(chosen.closest('form').action).pathname,
        value: chosen.value,
        question: chosen.dataset.confirm,
    }));

    await refused(page, action, question, async () => {
        await choosing.locator('select:has(option[data-confirm])').selectOption(value);
        await choosing.locator('button[type="submit"]').click();
    });
});
