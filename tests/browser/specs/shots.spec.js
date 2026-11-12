const { test } = require('@playwright/test');
const { impersonate } = require('../crawl');

const shots = process.env.ADZERCMS_SHOTS;
const paths = (process.env.ADZERCMS_SHOT_PATHS ?? '').split(/\s+/).filter(Boolean);
const operator = (path) => path === '/admin' || path.startsWith('/admin/');

test('[ACMS-TEST-0133] pages are shot as the operator and under the impersonated record', async ({ page }, info) => {
    test.skip(!shots || paths.length === 0, 'ADZERCMS_SHOTS and ADZERCMS_SHOT_PATHS name the shots');

    const shoot = async (path) => {
        await page.goto(path, { waitUntil: 'networkidle' });
        const window = page.viewportSize();
        const height = await page.evaluate(() => document.documentElement.scrollHeight);
        await page.setViewportSize({ width: window.width, height: Math.max(window.height, height) });
        await page.screenshot({ path: `${shots}/${info.project.name}-${paths.indexOf(path) + 1}.png` });
        await page.setViewportSize(window);
    };

    for (const path of paths.filter(operator)) {
        await shoot(path);
    }

    const cabinet = paths.filter((path) => !operator(path));
    if (cabinet.length > 0) {
        const home = await impersonate(page);
        for (const path of cabinet) {
            await shoot(path);
        }
        await page.goto(home, { waitUntil: 'domcontentloaded' });
        await page.locator('.impersonate-exit').click();
    }
});
