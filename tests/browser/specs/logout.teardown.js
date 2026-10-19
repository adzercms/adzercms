const fs = require('node:fs');
const { test: logout, expect } = require('@playwright/test');

logout('operator signs out', async ({ page }) => {
    await page.goto('/logout');
    await page.goto('/admin');

    await expect(page, 'the admin asks to sign in again').toHaveURL(/\/login/);
    fs.rmSync('.auth/operator.json', { force: true });
});
