const { test, expect } = require('@playwright/test');
const { WALK, crawl } = require('../crawl');

const pair = () => {
    const blocks = [...document.querySelectorAll('[data-live][id]')]
        .filter((block) => document.querySelectorAll(`[id="${CSS.escape(block.id)}"]`).length === 1);

    for (const held of blocks) {
        const spared = blocks.find((block) => !block.contains(held) && !held.contains(block));

        if (spared) {
            return { held: held.id, spared: spared.id };
        }
    }

    return null;
};

const frames = (message) => String(message).split('\n').filter(Boolean).map((line) => JSON.parse(line));

const tap = async (page) => {
    const hub = { socket: null, subscribed: [] };

    await page.routeWebSocket(() => true, (socket) => {
        const server = socket.connectToServer();
        const asked = new Map();
        hub.socket = socket;
        hub.subscribed = [];

        socket.onMessage((message) => {
            frames(message).filter(({ subscribe }) => subscribe).forEach(({ id, subscribe }) => asked.set(id, subscribe.channel));
            server.send(message);
        });
        server.onMessage((message) => {
            frames(message).filter(({ id, subscribe }) => subscribe && asked.has(id)).forEach(({ id }) => hub.subscribed.push(asked.get(id)));
            socket.send(message);
        });
    });

    return hub;
};

const publish = (hub, times) => {
    for (let sent = 0; sent < times; sent += 1) {
        hub.socket.send(JSON.stringify({ push: { channel: hub.subscribed[0], pub: { data: {} } } }));
    }
};

const marked = async (page, ids) => {
    const card = page.url().split('#')[0];
    const refreshes = { count: 0 };

    await page.route((url) => url.href === card, async (route) => {
        if (route.request().resourceType() !== 'fetch') {
            return route.fallback();
        }
        refreshes.count += 1;
        const probe = refreshes.count;
        const response = await route.fetch();
        const body = ids.reduce(
            (html, id) => html.replace(new RegExp(`(\\sid="${id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}")`), `$1 data-probe="${probe}"`),
            await response.text(),
        );
        const headers = Object.fromEntries(Object.entries(response.headers())
            .filter(([name]) => !['content-length', 'content-encoding'].includes(name)));

        return route.fulfill({ response, body, headers });
    });

    return refreshes;
};

test('[ACMS-TEST-0016][ACMS-TEST-0018][ACMS-UI-0058][ACMS-UI-0063] a series of hub events refreshes live blocks once and spares the block in editing', async ({ page }) => {
    test.setTimeout(900000);

    await crawl(page, '/admin', [], { ...WALK, stop: async () => await page.evaluate(pair) !== null });
    expect(await page.evaluate(pair), 'the admin walk meets no page with two live blocks').not.toBeNull();

    await page.route('**/*', (route) => (route.request().method() === 'GET' ? route.fallback() : route.abort()));
    const hub = await tap(page);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect.poll(() => hub.subscribed.length, { message: 'the page subscribes to the hub' }).toBeGreaterThan(0);

    const { held, spared } = await page.evaluate(pair);
    const refreshes = await marked(page, [held, spared]);
    const block = page.locator(`[id="${held}"]`);
    const field = await block.evaluateHandle((node) => node.appendChild(Object.assign(document.createElement('input'), { value: 'edited' })));

    publish(hub, 3);

    await expect(page.locator(`[id="${spared}"]`), 'an event brings fresh markup to a block out of editing').toHaveAttribute('data-probe', '1');
    expect(refreshes.count, 'a series of events refreshes the page once').toBe(1);
    await expect(block, 'the block with an edited field keeps its markup').not.toHaveAttribute('data-probe');

    await field.evaluate((input) => {
        input.value = input.defaultValue;
        input.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
    });
    await expect(block, 'the released block takes the fresh markup').toHaveAttribute('data-probe', '2');
});
