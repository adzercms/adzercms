const { test, expect } = require('@playwright/test');

const brand = page => page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--brand').trim());

const ink = locator => locator.evaluate(element => {
    const canvas = document.createElement('canvas').getContext('2d');
    canvas.fillStyle = getComputedStyle(element).color;
    canvas.fillRect(0, 0, 1, 1);

    return [...canvas.getImageData(0, 0, 1, 1).data].slice(0, 3).join(',');
});

test('[ACMS-UI-0088] a swatch and an own color repaint the page before saving', async ({ page }) => {
    await page.goto('/admin/settings?tab=brand');
    const swatch = page.locator('.palette input.swatch').nth(1);
    await swatch.check();
    await expect.poll(() => brand(page)).toBe(await swatch.getAttribute('data-color'));

    await page.locator('.palette [data-palette-own]').fill('#ffd400');
    await expect.poll(() => brand(page)).toBe('#ffd400');
});

test('[ACMS-UI-0087] the text on the accent is black or white by its lightness', async ({ page }) => {
    await page.goto('/admin/settings?tab=brand');
    await page.evaluate(() => { document.documentElement.dataset.theme = 'light'; });
    const save = page.locator('form > button[type="submit"]');

    await page.locator('.palette [data-palette-own]').fill('#0066cc');
    await expect.poll(() => ink(save)).toBe('255,255,255');

    await page.locator('.palette [data-palette-own]').fill('#ffd400');
    await expect.poll(() => ink(save)).toBe('0,0,0');
});
