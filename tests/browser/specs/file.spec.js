const { test, expect } = require('@playwright/test');

test('[ACMS-UI-0085] the file field names the picked file', async ({ page }) => {
    await page.goto('/admin/settings?tab=brand');
    const field = page.locator('.file-field').first();
    await field.locator('input[type="file"]').setInputFiles({ name: 'mark.png', mimeType: 'image/png', buffer: Buffer.from('') });
    await expect(field.locator('.file-names')).toHaveText('mark.png');
});
