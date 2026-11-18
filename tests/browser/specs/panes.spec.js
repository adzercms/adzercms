const { test, expect } = require('@playwright/test');

const AREA = `<div id="panes" data-panes>
    <nav class="tabs">
        <button type="button" class="tab active" data-pane="all">All</button>
        <button type="button" class="tab" data-pane="first">First</button>
        <button type="button" class="tab" data-pane="second">Second</button>
    </nav>
    <p data-pane-body="all first">one</p>
    <p data-pane-body="all second">two</p>
    <p data-pane-body="all first second">both</p>
</div>`;

test('[ACMS-UI-0161] a body belongs to every tab it names, the open tab hides the rest in place', async ({ page }) => {
    await page.goto('/admin/references');
    await page.locator('.panel-content').evaluate((content, html) => content.insertAdjacentHTML('afterbegin', html), AREA);
    const bodies = async () => (await page.locator('#panes [data-pane-body]').evaluateAll((found) => found.filter((body) => !body.hidden).map((body) => body.textContent)));

    await page.locator('#panes [data-pane="first"]').click();
    expect(await bodies()).toEqual(['one', 'both']);
    await expect(page.locator('#panes [data-pane="first"]')).toHaveClass(/active/);

    await page.locator('#panes [data-pane="second"]').click();
    expect(await bodies()).toEqual(['two', 'both']);

    await page.locator('#panes [data-pane="all"]').click();
    expect(await bodies()).toEqual(['one', 'two', 'both']);
});
