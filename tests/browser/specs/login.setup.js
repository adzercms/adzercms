const { test: login, expect } = require('@playwright/test');

login('operator signs in', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="_username"]', process.env.ADZERCMS_LOGIN ?? '');
    await page.fill('input[name="_password"]', process.env.ADZERCMS_PASSWORD ?? '');
    await page.click('button[type="submit"]');

    await expect(page).not.toHaveURL(/\/login/);
    await page.context().storageState({ path: '.auth/operator.json' });
});
