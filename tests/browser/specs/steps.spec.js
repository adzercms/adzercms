const { test, expect } = require('@playwright/test');

const PAGE = '/admin/references';
const ACTION = `${PAGE}/stepped`;
const SENT = `${PAGE}?sent`;

const FORM = `<form method="post" action="${ACTION}" id="stepped" data-steps>
    <section data-step="first" data-step-title="First"><input name="first" required></section>
    <section data-step="choice" data-step-title="Choice">
        <select name="kind"><option value="plain">plain</option><option value="extra">extra</option></select>
    </section>
    <section data-step="extra" data-step-title="Extra" data-when="kind=extra"><input name="extra" required></section>
    <section data-step="last" data-step-title="Last"><input name="last"></section>
    <div class="button-row">
        <button type="button" data-step-back>back</button>
        <button type="button" data-step-next>next</button>
        <button type="submit" data-step-submit>send</button>
    </div>
</form>`;

const mount = async (page, open = '') => {
    const sent = [];
    await page.route((url) => url.pathname === ACTION, (route) => {
        sent.push(route.request().postData());

        return route.fulfill({ status: 303, headers: { location: SENT } });
    });
    await page.locator('.panel-content').evaluate((content, [html, opened]) => {
        content.insertAdjacentHTML('afterbegin', html);
        const form = document.getElementById('stepped');
        if (opened) {
            form.dataset.stepOpen = opened;
        }
        form.dispatchEvent(new CustomEvent('htmx:load', { bubbles: true }));
    }, [FORM, open]);

    return sent;
};

const shown = (page) => page.locator('#stepped [data-step].current');
const titles = (page) => page.locator('#stepped .steps-nav li');

test.beforeEach(async ({ page }) => {
    await page.goto(PAGE);
});

test('[ACMS-UI-0107] steps open one by one, a step goes on only with its fields valid and the hidden one is skipped', async ({ page }) => {
    const sent = await mount(page);

    await expect(shown(page), 'the first step alone is shown').toHaveAttribute('data-step', 'first');
    await expect(titles(page), 'the titles list the shown steps').toHaveText(['First', 'Choice', 'Last']);
    await expect(page.locator('#stepped [data-step-back]')).toBeHidden();
    await expect(page.locator('#stepped [data-step-submit]')).toBeHidden();

    await page.locator('#stepped [data-step-next]').click();
    await expect(shown(page), 'an empty required field keeps its step').toHaveAttribute('data-step', 'first');

    await page.locator('#stepped input[name="first"]').fill('one');
    await page.locator('#stepped input[name="first"]').press('Enter');
    await expect(shown(page), 'Enter goes to the next step instead of sending').toHaveAttribute('data-step', 'choice');
    expect(sent, 'nothing is sent before the last step').toHaveLength(0);

    await page.locator('#stepped [data-step-next]').click();
    await expect(shown(page), 'the step hidden by its condition is skipped').toHaveAttribute('data-step', 'last');
    await expect(page.locator('#stepped [data-step-next]')).toBeHidden();
    await expect(page.locator('#stepped [data-step-submit]'), 'the last step sends').toBeVisible();

    await page.locator('#stepped [data-step-back]').click();
    await page.locator('#stepped select[name="kind"]').selectOption('extra');
    await expect(titles(page), 'the step shown by its condition joins the list').toHaveText(['First', 'Choice', 'Extra', 'Last']);
    await page.locator('#stepped [data-step-next]').click();
    await expect(shown(page)).toHaveAttribute('data-step', 'extra');

    await titles(page).first().click();
    await expect(shown(page), 'the title of a passed step goes back to it').toHaveAttribute('data-step', 'first');
});

test('[ACMS-UI-0114] a step change opens the top of the form', async ({ page }) => {
    await mount(page);
    await page.locator('#stepped').evaluate((form) => ['first', 'choice'].forEach((key) => form
        .querySelector(`[data-step="${key}"]`).append(Object.assign(document.createElement('div'), { style: 'height: 3000px' }))));
    await page.locator('#stepped input[name="first"]').fill('one');
    await page.locator('#stepped [data-step-next]').scrollIntoViewIfNeeded();
    await expect(page.locator('#stepped .steps-nav')).not.toBeInViewport();

    await page.locator('#stepped [data-step-next]').click();
    await expect(shown(page)).toHaveAttribute('data-step', 'choice');
    await expect(page.locator('#stepped .steps-nav'), 'the next step opens at the top of the form').toBeInViewport();
});

test('[ACMS-UI-0115] sending with a field of another step left invalid opens its step', async ({ page }) => {
    const sent = await mount(page, 'last');

    await page.locator('#stepped [data-step-submit]').click();
    await expect(shown(page), 'the step of the empty required field opens').toHaveAttribute('data-step', 'first');
    await expect(page.locator('#stepped input[name="first"]')).toBeFocused();
    expect(sent, 'nothing is sent').toHaveLength(0);
});

test('[ACMS-UI-0107] a refused form opens the step named by the server', async ({ page }) => {
    await mount(page, 'last');

    await expect(shown(page)).toHaveAttribute('data-step', 'last');
    await expect(page.locator('#stepped .steps-nav li.done'), 'the steps before it are passed').toHaveCount(2);
});

test('[ACMS-UI-0113] a reload keeps the step and what was entered until the form is sent', async ({ page }) => {
    await mount(page);
    await page.locator('#stepped input[name="first"]').fill('one');
    await page.locator('#stepped [data-step-next]').click();
    await page.locator('#stepped select[name="kind"]').selectOption('extra');

    await page.reload();
    await mount(page);
    await expect(shown(page), 'the reloaded form opens at its step').toHaveAttribute('data-step', 'choice');
    await expect(page.locator('#stepped input[name="first"]')).toHaveValue('one');
    await expect(titles(page), 'the restored choice brings back its step').toHaveText(['First', 'Choice', 'Extra', 'Last']);

    await page.reload();
    const sent = await mount(page, 'last');
    await expect(shown(page), 'the step named by the server outranks the step of the draft').toHaveAttribute('data-step', 'last');
    await page.locator('#stepped').evaluate((form) => {
        form.elements.extra.value = 'two';
    });
    await page.locator('#stepped [data-step-submit]').click();
    await page.waitForURL(SENT);
    expect(sent).toHaveLength(1);

    await mount(page);
    await expect(shown(page), 'the sent form starts anew').toHaveAttribute('data-step', 'first');
    await expect(page.locator('#stepped input[name="first"]')).toHaveValue('');
});
