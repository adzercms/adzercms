const { test, expect } = require('@playwright/test');

test.use({ storageState: { cookies: [], origins: [] } });

const failures = {
    'a 5xx answer': route => route.fulfill({ status: 503, contentType: 'text/plain', body: 'down' }),
    'a network failure': route => route.abort(),
};

for (const [failure, fail] of Object.entries(failures)) {
    test(`[ACMS-CORE-1203][ACMS-US-0070] ${failure} of the application shows a guest the page the worker saved`, async ({ page, context }) => {
        await page.goto('/');
        test.skip(await page.locator('script[data-worker]').count() === 0, 'the root of the instance is no site page');
        await page.evaluate(() => navigator.serviceWorker.ready);

        await page.reload();
        const title = await page.title();
        await expect.poll(() => page.evaluate(async () => (await caches.match(location.href)) !== undefined), 'the worker saves the page of a guest').toBe(true);

        const preloaded = await page.evaluate(async () => {
            const { navigationPreload } = await navigator.serviceWorker.ready;
            const { enabled } = await navigationPreload.getState();
            await navigationPreload.disable();

            return enabled;
        });
        expect(preloaded, 'the worker takes a navigation by navigation preload').toBe(true);

        let intercepted = 0;
        await context.route(url => url.pathname === '/', route => {
            intercepted++;

            return fail(route);
        });
        const response = await page.reload();
        expect(intercepted, 'the request of the worker meets the failure, the routing sees no navigation preload').toBeGreaterThan(0);
        expect(response.fromServiceWorker()).toBe(true);
        await expect(page).toHaveTitle(title);
    });
}
