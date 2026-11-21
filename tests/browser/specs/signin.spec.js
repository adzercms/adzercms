const { test, expect } = require('@playwright/test');

test.use({ storageState: { cookies: [], origins: [] } });

const faults = (page) => {
    const caught = [];
    page.on('console', (message) => message.type() === 'error' && caught.push(message.text()));
    page.on('pageerror', (error) => caught.push(error.message));

    return caught;
};

const entry = async (page) => {
    await page.goto('/');
    const link = page.locator('a[data-record*="/login"]').first();
    test.skip(await link.count() === 0, 'the page has no sign-in of the header');
    await link.click();

    return page.locator('#record[open]');
};

test('[ACMS-UI-0176][ACMS-UI-0177][ACMS-CORE-1201][ACMS-US-0002] the sign-in of the header opens a window over the page, its links stay in it, the sign-in leads back to the page', async ({ page }) => {
    const caught = faults(page);
    const window = await entry(page);
    const here = page.url();

    await expect(window.locator('input[name="_username"]'), 'the window opens the login form').toBeFocused();
    await window.locator('.auth-row a').click();
    await expect(window.locator('input[name="login"]'), 'the recovery opens in the same window').toBeVisible();
    await window.locator('.auth-links a').click();
    await expect(window.locator('input[name="_username"]'), 'the way back opens in the same window').toBeVisible();
    expect(page.url(), 'the window leaves the page in place').toBe(here);

    await window.locator('input[name="_username"]').fill(process.env.ADZERCMS_LOGIN ?? '');
    await window.locator('input[name="_password"]').fill('wrong');
    await window.locator('button[type="submit"]').click();
    await expect(window.locator('.flash-error'), 'a refusal stays in the window').toBeVisible();

    await window.locator('input[name="_password"]').fill(process.env.ADZERCMS_PASSWORD ?? '');
    await window.locator('button[type="submit"]').click();
    await expect(page.locator('a[data-record*="/login"]'), 'the header of the page names the cabinet after the sign-in').toHaveCount(0);
    expect(page.url(), 'the sign-in leads back to the page').toBe(here);
    expect(caught, 'the window runs without script errors').toEqual([]);

    await page.goto('/logout');
});

test('[ACMS-CORE-1202][ACMS-UI-0176] the captcha of a window draws on each opening', async ({ page }) => {
    const caught = faults(page);
    const window = await entry(page);

    for (const opening of [1, 2]) {
        await window.locator('.auth-row a').click();
        await expect(window.locator('input[name="login"]'), `the recovery opens at the opening ${opening}`).toBeVisible();
        const widget = window.locator('[data-captcha]');
        test.skip(await widget.count() === 0, 'the instance has no captcha');
        await expect(widget.locator(':scope > *').first(), `the captcha draws at the opening ${opening}`).toBeAttached();
        await window.locator('.auth-links a').click();
    }
    expect(caught, 'the captcha runs without script errors').toEqual([]);
});
