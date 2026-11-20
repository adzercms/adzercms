const { test, expect } = require('@playwright/test');

test('[ACMS-UI-0174] pointing at a chart names the moment and the values of its visible lines', async ({ page }) => {
    await page.goto('/admin/load');
    const chart = page.locator('.chart').first();
    test.skip(await chart.count() === 0, 'the instance shows no charts');

    const field = chart.locator('.chart-field');
    const probe = field.locator('.chart-probe');
    await expect(probe).toBeHidden();

    await field.hover();
    await expect(probe).toBeVisible();
    await expect(probe.locator('.chart-moment')).not.toBeEmpty();
    await expect(probe.locator('.chart-value:visible output').first()).not.toBeEmpty();

    await chart.locator('.chart-toggle input').first().uncheck();
    await field.hover();
    await expect(probe.locator('.chart-value').first()).toBeHidden();

    await page.mouse.move(0, 0);
    await expect(probe).toBeHidden();
});
