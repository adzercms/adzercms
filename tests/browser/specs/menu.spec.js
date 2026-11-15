const { test, expect } = require('@playwright/test');

test('[ACMS-UI-0156] the narrow-screen menu opens as a window under the header with the section submenu under its item', async ({ page }, info) => {
    test.skip(info.project.name !== 'phone', 'the menu window belongs to the narrow screen');

    await page.goto('/admin/settings', { waitUntil: 'networkidle' });
    await page.locator('.menu-toggle').click();

    const sidebar = page.locator('.panel-sidebar');
    await expect(sidebar).toBeVisible();
    const header = await page.locator('.panel-header').boundingBox();
    const window = await sidebar.boundingBox();
    expect(Math.round(window.y)).toBe(Math.round(header.y + header.height));
    await expect(sidebar.locator('.nav-section-title').first()).toBeInViewport({ ratio: 1 });
    await expect(sidebar.locator('.nav-item.active + .nav-submenu')).toBeVisible();
});
