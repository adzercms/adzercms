const { test, expect } = require('@playwright/test');

test.use({ storageState: { cookies: [], origins: [] } });

test('[ACMS-BLD-0015][ACMS-BLD-0017][ACMS-US-0079] a card of the catalog keeps a column of its grid, the copy action of the show takes a guest to the sign-in', async ({ page }) => {
    const faults = [];
    page.on('console', (message) => message.type() === 'error' && faults.push(message.text()));
    page.on('pageerror', (error) => faults.push(error.message));

    const listing = await page.goto('/templates');
    test.skip(!listing?.ok(), 'the instance has no builder module');
    const card = page.locator('[data-template]').first();
    test.skip(await card.count() === 0, 'the catalog has no template');

    const grid = card.locator('xpath=..');
    const columns = await grid.evaluate((node) => getComputedStyle(node).gridTemplateColumns.split(' ').length);
    expect((await card.boundingBox()).width, 'a single card takes one column, not the whole row').toBeLessThanOrEqual((await grid.boundingBox()).width / columns + 1);

    await card.locator('a').click();
    await expect(page).toHaveURL(/\/templates\/[0-9a-f]+/);
    await page.locator('[data-template-copy] button[type="submit"]').click();
    await expect(page, 'a guest signs in before the copy').toHaveURL(/\/login$/);
    expect(faults, 'the catalog and the show run without script errors').toEqual([]);
});
