const { test, expect } = require('@playwright/test');

const TEXTS = '/admin/references';
const SENDING = `${TEXTS}/sending`;

const hold = async (page, matches, answer) => {
    const sent = [];
    let release;
    const released = new Promise((resolve) => { release = resolve; });

    await page.route((url) => matches(url.pathname), async (route) => {
        if (route.request().method() !== 'POST') {
            return route.continue();
        }
        sent.push(route.request().url());
        await released;

        return route.fulfill(answer);
    });

    return { sent, release };
};

const progress = (page) => page.locator('.running-bar.page');

test.beforeEach(async ({ page }) => {
    await page.goto(TEXTS);
});

test('[ACMS-UI-0092] a form sent by navigation goes once until its page comes, its button keeps the look', async ({ page }) => {
    const { sent, release } = await hold(page, (path) => path === SENDING, { contentType: 'text/html', body: '<!doctype html><title>sent</title>' });
    await page.locator('.panel-content').evaluate((content, action) => content.insertAdjacentHTML(
        'afterbegin',
        `<form method="post" action="${action}" id="sending"><button type="submit">!</button></form>`,
    ), SENDING);
    const pressed = await page.locator('#sending button').evaluate(async (button) => {
        const idle = getComputedStyle(button).opacity;
        button.click();
        await new Promise((resolve) => { setTimeout(resolve, 100); });
        const state = {
            progress: document.querySelector('.running-bar.page') !== null,
            enabled: !button.disabled,
            look: getComputedStyle(button).opacity === idle,
        };
        button.click();

        return state;
    });

    expect(pressed, 'the frame shows the progress, the sent button stays enabled in its look').toEqual({ progress: true, enabled: true, look: true });
    await expect.poll(() => sent.length).toBe(1);
    release();
    await page.waitForURL(`**${SENDING}`);
    expect(sent, 'the second press sends nothing').toHaveLength(1);
});

test('[ACMS-UI-0092] a fragment form goes once until its answer, then again', async ({ page }) => {
    const { sent, release } = await hold(page, (path) => path.startsWith(`${TEXTS}/`), { status: 204 });
    await page.locator('tr[data-record]').first().click();
    const button = page.locator('#record button[type="submit"]:not([name])');
    await expect(button).toBeVisible();

    await button.click();
    await expect.poll(() => sent.length).toBe(1);
    await button.click();
    release();
    await expect(progress(page), 'the answer ends the progress').toHaveCount(0);
    expect(sent, 'the second press before the answer sends nothing').toHaveLength(1);

    await button.click();
    await expect.poll(() => sent.length, 'the press after the answer sends again').toBe(2);
});

test('[ACMS-UI-0092] a dialog form closes its dialog without the progress', async ({ page }) => {
    await page.locator('tr[data-record]').first().click();
    const record = page.locator('#record');
    await expect(record).toBeVisible();

    await record.locator('.modal-close').click();
    await expect(record).toBeHidden();
    await expect(progress(page)).toHaveCount(0);
});
