const { test, expect } = require('@playwright/test');
const { WALK, crawl, records } = require('../crawl');

const impersonate = async (page) => {
    const served = await records(page);
    expect(served.length, 'the admin lists no records').toBeGreaterThan(0);

    for (const record of served) {
        await page.goto(record, { waitUntil: 'domcontentloaded' });
        const switcher = page.locator('a[href*="_switch_user="]');

        if (await switcher.count() === 0) {
            continue;
        }
        await switcher.first().click();

        if (await page.locator('.impersonate-bar').isVisible()) {
            return new URL(page.url()).pathname;
        }
    }

    throw new Error('no record lets the operator in — the cabinet is not walked');
};

test('[ACMS-TEST-0016][ACMS-TEST-0017][ACMS-TEST-0071][ACMS-UI-0045] screens fit the window width with a clean console', async ({ page }) => {
    test.setTimeout(900000);

    const faults = [];
    const operator = await crawl(page, '/admin', faults, WALK);
    expect.soft(operator, 'the admin walk does not reach record cards')
        .toEqual(expect.arrayContaining(['/admin/users/*']));

    const home = await impersonate(page);
    const cabinet = await crawl(page, home, faults, WALK);
    await page.goto(home, { waitUntil: 'domcontentloaded' });
    await page.locator('.impersonate-exit').click();
    await expect(page.locator('.impersonate-bar'), 'the impersonation ends').toHaveCount(0);

    expect.soft(cabinet.length, 'the cabinet walk does not leave its home').toBeGreaterThan(1);
    expect.soft(Math.max(operator.length, cabinet.length),
        'the walk hits its limit — part of the screens stays unseen').toBeLessThan(WALK.limit);
    expect(faults, 'screen defects').toEqual([]);
});
