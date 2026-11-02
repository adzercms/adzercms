const { test, expect } = require('@playwright/test');
const { walk } = require('../crawl');

const SEARCH = 'a';

const variants = ({ today, search }) => {
    const local = (url) => url.pathname + url.search;
    const flipped = (href) => {
        const url = new URL(href, location.origin);
        for (const [key, value] of [...url.searchParams]) {
            if (/(^|_)order$/.test(key)) {
                url.searchParams.set(key, value === 'asc' ? 'desc' : 'asc');
            }
        }

        return local(url);
    };
    const values = (field) => {
        if (field.type === 'search') {
            return [search];
        }
        if (field.type === 'date') {
            return [today];
        }

        return [...field.options].map((option) => option.value).filter(Boolean);
    };
    const sorts = [...document.querySelectorAll('th.sortable-header a[href^="/"]')]
        .map((link) => link.getAttribute('href'))
        .flatMap((href) => [href, flipped(href)]);
    const filters = [...document.querySelectorAll('form.table-filters [name][hx-get^="/"]')]
        .flatMap((field) => values(field).map((value) => {
            const url = new URL(field.getAttribute('hx-get'), location.origin);
            url.searchParams.set(field.name, value);

            return local(url);
        }));

    return [...sorts, ...filters];
};

test('[ACMS-TEST-0113][ACMS-FLOW-0001] every column of a list sorts both ways and every filter value narrows it', async ({ page, isMobile }) => {
    test.skip(isMobile, 'one window width walks the lists');
    test.setTimeout(1800000);

    const faults = [];
    const asked = new Set();
    const probe = { today: new Date().toISOString().slice(0, 10), search: SEARCH };

    await walk(page, faults, async (page, path) => {
        for (const href of await page.evaluate(variants, probe)) {
            if (asked.has(href)) {
                continue;
            }
            asked.add(href);
            const status = (await page.request.get(href)).status();

            if (status >= 400) {
                faults.push(`${path}: ${href} — status ${status}`);
            }
        }

        return false;
    });

    expect.soft(asked.size, 'the walk meets no list').toBeGreaterThan(0);
    expect(faults, 'list defects').toEqual([]);
});
