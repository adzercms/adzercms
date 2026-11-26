const { test, expect } = require('@playwright/test');

const PROBE = '/seen-probe';

test('[ACMS-UI-0191][ACMS-TEST-0018] a seen form goes once, a hidden page sends it when shown', async ({ page }) => {
    await page.route(`**${PROBE}`, (route) => route.fulfill({ status: 204 }));
    await page.goto('/admin', { waitUntil: 'networkidle' });

    const hidden = await page.evaluate((action) => {
        window.reports = [];
        const send = window.fetch;
        window.fetch = (...call) => {
            window.reports.push(String(call[0]));
            return send(...call);
        };
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' });

        const block = document.createElement('div');
        block.innerHTML = `<form method="post" action="${action}" data-seen hidden></form>`;
        document.body.append(block);
        block.dispatchEvent(new CustomEvent('htmx:load', { bubbles: true }));

        return window.reports.length;
    }, PROBE);
    expect(hidden, 'a hidden page holds the report').toBe(0);

    const shown = await page.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'visible' });
        document.dispatchEvent(new Event('visibilitychange'));
        document.dispatchEvent(new Event('visibilitychange'));
        document.querySelector('form[data-seen]').parentElement.dispatchEvent(new CustomEvent('htmx:load', { bubbles: true }));

        return window.reports;
    });
    expect(shown, 'the shown page reports the form once').toEqual([expect.stringContaining(PROBE)]);
});
