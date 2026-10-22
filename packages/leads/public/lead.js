import htmx from 'htmx.org';

const ANCHOR = '#lead';
const PARAMETER = /^lead([A-Z].*)$/;
const dialog = document.getElementById('lead');

const query = opener => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(opener?.dataset ?? {})) {
        const name = key === 'lead' ? 'topic' : key.match(PARAMETER)?.[1];
        if (name && value !== '') {
            params.set(name.charAt(0).toLowerCase() + name.slice(1), value);
        }
    }

    return params;
};

const open = opener => htmx.ajax('GET', `${dialog.dataset.window}?${query(opener)}`, {target: dialog, swap: 'innerHTML'});

const anchored = link => link.hash === ANCHOR && link.origin === location.origin
    && link.pathname === location.pathname && link.search === location.search;

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-lead], a[href]');
    if (opener && (opener.dataset.lead !== undefined || anchored(opener))) {
        event.preventDefault();
        open(opener.dataset.lead === undefined ? null : opener);
    }
});

const arrived = () => {
    if (location.hash === ANCHOR) {
        history.replaceState(history.state, '', location.pathname + location.search);
        open(null);
    }
};

window.addEventListener('hashchange', arrived);
arrived();

document.addEventListener('htmx:afterSwap', event => {
    if (event.target !== dialog) {
        return;
    }

    if (!dialog.open) {
        dialog.showModal();
    }
    (dialog.querySelector('input:not([type="hidden"]):not([tabindex="-1"]), textarea') ?? dialog).focus();
});
