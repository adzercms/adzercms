const { expect } = require('@playwright/test');

const UNSHAPED = /(^|_)(sort|order|page)$|^(year|month|from|to)$/;
const DOWNLOAD = 'Download is starting';

const links = () => [...document.querySelectorAll('a[href^="/"], [data-href^="/"]')]
    .map((el) => el.getAttribute('href') ?? el.getAttribute('data-href'))
    .filter((href) => href && !href.startsWith('//'));

const shape = (href) => {
    const url = new URL(href, 'https://instance.test');
    const path = url.pathname.split('/')
        .map((part) => (/^[a-z]?\d+$/i.test(part) || (part.includes('.') && !part.endsWith('.php')) ? '*' : part))
        .join('/');
    const kept = [...url.searchParams]
        .filter(([key]) => !UNSHAPED.test(key))
        .map(([key, value]) => `${key}=${value}`)
        .sort()
        .join('&');

    return kept ? `${path}?${kept}` : path;
};

const open = async (page, path) => {
    try {
        return await page.goto(path, { waitUntil: 'domcontentloaded' });
    } catch (error) {
        if (String(error.message).includes(DOWNLOAD)) {
            return null;
        }
        throw error;
    }
};

const WALK = {
    limit: 300,
    skip: ['/logout', '/login', '_switch_user'],
};

const crawl = async (page, seed, faults, { limit, skip, visit = async () => false }) => {
    const wanted = (href) => !skip.some((mark) => href.includes(mark));
    const queue = [seed];
    const seen = new Map();
    let path = seed;

    const logged = (message) => message.type() === 'error' && faults.push(`${path}: console — ${message.text()}`);
    const thrown = (error) => faults.push(`${path}: script — ${error.message}`);
    page.on('console', logged);
    page.on('pageerror', thrown);

    try {
        while (queue.length && seen.size < limit) {
            path = queue.shift();
            const mark = shape(path);

            if (seen.has(mark)) {
                continue;
            }
            seen.set(mark, path);

            const response = await open(page, path);
            if (response === null) {
                continue;
            }
            const status = response?.status() ?? 0;

            if (status >= 400) {
                faults.push(`${path}: status ${status}`);
                continue;
            }
            if (!(response?.headers()['content-type'] ?? '').startsWith('text/html')) {
                continue;
            }

            await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

            if (await visit(page, path)) {
                break;
            }

            for (const href of (await page.evaluate(links)).filter(wanted)) {
                if (!seen.has(shape(href))) {
                    queue.push(href);
                }
            }
        }
    } finally {
        page.off('console', logged);
        page.off('pageerror', thrown);
    }

    return [...seen.keys()];
};

const records = async (page) => {
    await page.goto(process.env.ADZERCMS_RECORDS || '/admin/users', { waitUntil: 'networkidle' });

    return [...new Set(await page.locator('tbody tr[data-href^="/admin/users/"], tbody tr a[href^="/admin/users/"]')
        .evaluateAll((found) => found.map((el) => el.getAttribute('data-href') ?? el.getAttribute('href'))))];
};

const impersonate = async (page) => {
    const served = await records(page);
    expect(served.length, 'the admin lists no records').toBeGreaterThan(0);

    for (const record of served) {
        await page.goto(record, { waitUntil: 'domcontentloaded' });
        const switcher = page.locator('a[href*="_switch_user="]');

        if (await switcher.count() === 0) {
            continue;
        }
        await switcher.first().click();

        if (await page.locator('.impersonate-bar').isVisible()) {
            return new URL(page.url()).pathname;
        }
    }

    throw new Error('no record lets the operator in — the cabinet is not walked');
};

const walk = async (page, faults, visit) => {
    const operator = await crawl(page, '/admin', faults, { ...WALK, visit });
    expect.soft(operator, 'the admin walk does not reach record cards')
        .toEqual(expect.arrayContaining(['/admin/users/*']));

    const home = await impersonate(page);
    const cabinet = await crawl(page, home, faults, { ...WALK, visit });
    await page.goto(home, { waitUntil: 'domcontentloaded' });
    await page.locator('.impersonate-exit').click();
    await expect(page.locator('.impersonate-bar'), 'the impersonation ends').toHaveCount(0);

    expect.soft(cabinet.length, 'the cabinet walk does not leave its home').toBeGreaterThan(1);
    expect.soft(Math.max(operator.length, cabinet.length),
        'the walk hits its limit — part of the screens stays unseen').toBeLessThan(WALK.limit);
};

module.exports = { WALK, crawl, walk };
