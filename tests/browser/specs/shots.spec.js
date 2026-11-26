const { test } = require('@playwright/test');
const { impersonate } = require('../crawl');

const shots = process.env.ADZERCMS_SHOTS;
const paths = (process.env.ADZERCMS_SHOT_PATHS ?? '').split(/\s+/).filter(Boolean);
const site = (path) => /^https?:\/\//.test(path);
const operator = (path) => process.env.ADZERCMS_SHOT_AS === 'operator' || path === '/admin' || path.startsWith('/admin/');

const shoot = async (page, info, path) => {
    await page.goto(path, { waitUntil: 'networkidle' });
    const window = page.viewportSize();
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    await page.setViewportSize({ width: window.width, height: Math.max(window.height, height) });
    await page.screenshot({ path: `${shots}/${info.project.name}-${paths.indexOf(path) + 1}.png` });
    await page.setViewportSize(window);
};

test.describe('site', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('[ACMS-TEST-0133] site pages are shot as a guest', async ({ page }, info) => {
        const pages = paths.filter(site);
        test.skip(!shots || pages.length === 0, 'ADZERCMS_SHOTS and full addresses of ADZERCMS_SHOT_PATHS name the site shots');

        for (const path of pages) {
            await shoot(page, info, path);
        }
    });
});

test('[ACMS-TEST-0133] cabinet pages are shot as the operator and under the impersonated record', async ({ page }, info) => {
    const pages = paths.filter((path) => !site(path));
    test.skip(!shots || pages.length === 0, 'ADZERCMS_SHOTS and paths of ADZERCMS_SHOT_PATHS name the cabinet shots');

    for (const path of pages.filter(operator)) {
        await shoot(page, info, path);
    }

    const cabinet = pages.filter((path) => !operator(path));
    if (cabinet.length > 0) {
        const home = await impersonate(page);
        for (const path of cabinet) {
            await shoot(page, info, path);
        }
        await page.goto(home, { waitUntil: 'domcontentloaded' });
        await page.locator('.impersonate-exit').click();
    }
});
