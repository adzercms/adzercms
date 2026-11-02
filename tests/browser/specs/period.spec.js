const { test, expect } = require('@playwright/test');
const { WALK, crawl } = require('../crawl');

const PERIOD = 'form.table-filters:has(select[name="scale"])';

test('[ACMS-TEST-0016][ACMS-UI-0012] a period field change redraws the page in place', async ({ page }) => {
    test.setTimeout(900000);

    const form = page.locator(PERIOD).first();
    await crawl(page, '/admin', [], { ...WALK, stop: async () => await form.count() > 0 });
    expect(await form.count(), 'the admin walk meets no page period').toBeGreaterThan(0);

    const scale = form.locator('select[name="scale"]');
    const target = await scale.inputValue() === 'year' ? 'month' : 'year';
    await page.evaluate(() => { window.unreloaded = true; });

    await scale.selectOption(target);

    await expect(page, 'the period lands in the address').toHaveURL(new RegExp(`[?&]scale=${target}(&|$)`));
    const month = form.locator('select[name="month"]');
    await (target === 'year' ? expect(month, 'the redrawn form hides the month of a year').toBeHidden() : expect(month).toBeVisible());
    expect(await page.evaluate(() => window.unreloaded), 'the page is not reloaded').toBe(true);
});
