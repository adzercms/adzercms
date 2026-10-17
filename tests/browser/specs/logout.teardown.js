const fs = require('node:fs');
const { test: logout, expect } = require('@playwright/test');

logout('operator signs out', async ({ page }) => {
    await page.goto('/logout');

    await expect(page).toHaveURL(/\/login/);
    fs.rmSync('.auth/operator.json', { force: true });
});
