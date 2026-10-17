const UNSHAPED = /(^|_)(sort|order|page)$|^(year|month|from|to)$/;
const DOWNLOAD = 'Download is starting';

const measure = () => {
    const width = document.documentElement.clientWidth;
    const scrolled = (el) => {
        for (let node = el.parentElement; node; node = node.parentElement) {
            if (getComputedStyle(node).overflowX !== 'visible') {
                return true;
            }
        }
        return false;
    };
    const outside = [...document.querySelectorAll('body *')]
        .filter((el) => el.getClientRects().length > 0)
        .filter((el) => Math.round(el.getBoundingClientRect().right) > width + 1)
        .filter((el) => !scrolled(el))
        .map((el) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}`);

    const stretched = [...document.querySelectorAll('input[type="radio"], input[type="checkbox"]')]
        .filter((el) => el.getClientRects().length > 0)
        .filter((el) => {
            const box = el.getBoundingClientRect();

            return box.width > 32 || box.height > 32;
        })
        .map((el) => `${el.type}[${el.name || '—'}]`);

    const cells = [...document.querySelectorAll('td, th')]
        .filter((el) => el.getClientRects().length > 0)
        .filter((el) => getComputedStyle(el).display !== 'table-cell')
        .map((el) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}`);

    const counted = new Map();
    document.querySelectorAll('[id]').forEach((el) => counted.set(el.id, (counted.get(el.id) ?? 0) + 1));

    return {
        repeated: [...counted].filter(([, count]) => count > 1).map(([id]) => id).slice(0, 4),
        overflow: document.documentElement.scrollWidth - width,
        outside: [...new Set(outside)].slice(0, 4),
        stretched: [...new Set(stretched)].slice(0, 4),
        cells: [...new Set(cells)].slice(0, 4),
    };
};

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

const crawl = async (page, seed, faults, { limit, skip, stop = async () => false }) => {
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

            const facts = await page.evaluate(measure);

            if (facts.overflow > 0 || facts.outside.length > 0) {
                faults.push(`${path}: +${facts.overflow}px ${facts.outside.join(', ')}`);
            }

            if (facts.stretched.length > 0) {
                faults.push(`${path}: stretched switch — ${facts.stretched.join(', ')}`);
            }

            if (facts.cells.length > 0) {
                faults.push(`${path}: table cell out of the cell role — ${facts.cells.join(', ')}`);
            }

            if (facts.repeated.length > 0) {
                faults.push(`${path}: repeated id — ${facts.repeated.join(', ')}`);
            }

            if (await stop(page)) {
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

module.exports = { WALK, crawl, records };
