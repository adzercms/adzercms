const { test, expect } = require('@playwright/test');

test('[ACMS-UI-0085] the file field names the picked file', async ({ page }) => {
    await page.goto('/admin/settings/brand');
    const field = page.locator('.file-field').first();
    await field.locator('input[type="file"]').setInputFiles({ name: 'mark.png', mimeType: 'image/png', buffer: Buffer.from('') });
    await expect(field.locator('.file-names')).toHaveText('mark.png');
});

test('[ACMS-UI-0106] the picked file goes to the server at once and the form carries its key', async ({ page }) => {
    await page.goto('/admin/settings/brand');
    const field = page.locator('.file-field').first();
    await field.locator('input[type="file"]').setInputFiles({ name: 'mark.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64') });
    await expect(field.locator('input[name="_uploads[]"]')).toHaveCount(1);
    await expect(field.locator('.file-progress')).toBeHidden();
    await expect(field.locator('input[type="file"]')).not.toHaveAttribute('name');
});
