const { test, expect } = require('@playwright/test');
const { walk } = require('../crawl');

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

    const lists = [...document.querySelectorAll('.table-responsive')]
        .filter((el) => el.getClientRects().length > 0 && el.scrollWidth > el.clientWidth + 1)
        .map((el) => {
            const rows = [...el.querySelectorAll('tbody tr')];
            const columns = [...el.querySelectorAll('thead th')]
                .map((th, index) => ({ th, index }))
                .filter(({ th }) => th.getClientRects().length > 0)
                .map(({ th, index }) => {
                    const word = rows.flatMap((tr) => (tr.children[index]?.innerText ?? '').split(/\s+/))
                        .reduce((longest, token) => (token.length > longest.length ? token : longest), '');

                    return `${th.textContent.trim() || th.className.split(' ')[0] || '—'} ${Math.round(th.getBoundingClientRect().width)}${word ? ` «${word.slice(0, 32)}»` : ''}`;
                });

            return `${el.closest('[id]')?.id ?? 'table'} +${el.scrollWidth - el.clientWidth}px [${columns.join(', ')}]`;
        });

    const counted = new Map();
    document.querySelectorAll('[id]').forEach((el) => counted.set(el.id, (counted.get(el.id) ?? 0) + 1));

    const logo = document.querySelector('.header-logo');
    const column = [...document.querySelectorAll('.panel-sidebar .nav-item, .panel-content > *')]
        .find((el) => el.getClientRects().length > 0);
    const left = column?.getBoundingClientRect().left ?? 0;

    return {
        edge: logo && left > 0 ? Math.round(left - logo.getBoundingClientRect().left) : 0,
        repeated: [...counted].filter(([, count]) => count > 1).map(([id]) => id).slice(0, 4),
        overflow: document.documentElement.scrollWidth - width,
        outside: [...new Set(outside)].slice(0, 4),
        stretched: [...new Set(stretched)].slice(0, 4),
        cells: [...new Set(cells)].slice(0, 4),
        lists,
    };
};

const laidOut = (faults) => async (page, path) => {
    const facts = await page.evaluate(measure);

    if (facts.overflow > 0 || facts.outside.length > 0) {
        faults.push(`${path}: +${facts.overflow}px ${facts.outside.join(', ')}`);
    }

    if (facts.edge !== 0) {
        faults.push(`${path}: column ${facts.edge}px off the logo`);
    }

    if (facts.stretched.length > 0) {
        faults.push(`${path}: stretched switch — ${facts.stretched.join(', ')}`);
    }

    if (facts.cells.length > 0) {
        faults.push(`${path}: table cell out of the cell role — ${facts.cells.join(', ')}`);
    }

    if (facts.lists.length > 0) {
        faults.push(`${path}: list scrolls sideways — ${facts.lists.join(', ')}`);
    }

    if (facts.repeated.length > 0) {
        faults.push(`${path}: repeated id — ${facts.repeated.join(', ')}`);
    }

    return false;
};

test('[ACMS-TEST-0016][ACMS-TEST-0017][ACMS-TEST-0071][ACMS-TEST-0114][ACMS-UI-0045] screens fit the window width with a clean console', async ({ page }) => {
    test.setTimeout(900000);

    const faults = [];
    await walk(page, faults, laidOut(faults));

    expect(faults, 'screen defects').toEqual([]);
});
