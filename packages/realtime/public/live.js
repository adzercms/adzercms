import htmx from 'htmx.org';

const script = document.querySelector('script[data-client]');
const PAUSE = 400;
const TYPING = 'input, textarea, select, [contenteditable="true"]';
const FIELDS = 'input, textarea, select';

const blocks = () => [...document.querySelectorAll('[data-live][id]')];

const changed = field => {
    if (field.type === 'file') {
        return field.files.length > 0;
    }
    if (field.type === 'checkbox' || field.type === 'radio') {
        return field.checked !== field.defaultChecked;
    }
    if (field.tagName === 'SELECT' && field.multiple) {
        return [...field.options].some(option => option.selected !== option.defaultSelected);
    }
    if (field.tagName === 'SELECT') {
        return field.selectedIndex !== Math.max([...field.options].findIndex(option => option.defaultSelected), 0);
    }

    return field.value !== field.defaultValue;
};

const held = block => (block.contains(document.activeElement) && document.activeElement.matches(TYPING))
    || [...block.querySelectorAll(FIELDS)].some(changed);

const pending = new Set();
let timer = null;
let busy = false;
let again = false;
let stale = false;

const swap = (block, fresh) => {
    const next = fresh.getElementById(block.id);

    if (!next || next.outerHTML === block.outerHTML) {
        pending.delete(block.id);

        return;
    }
    if (held(block)) {
        pending.add(block.id);

        return;
    }

    pending.delete(block.id);
    const node = document.importNode(next, true);
    block.replaceWith(node);
    htmx.process(node);
};

const refresh = async () => {
    busy = true;
    again = false;

    try {
        const response = await fetch(window.location.href, {credentials: 'same-origin'});

        if (response.ok && !response.redirected) {
            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
            blocks().forEach(block => swap(block, fresh));
        }
    } catch {
        stale = true;
    }

    busy = false;

    if (again) {
        schedule();
    }
};

const schedule = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        if (document.visibilityState !== 'visible') {
            stale = true;
        } else if (busy) {
            again = true;
        } else {
            refresh();
        }
    }, PAUSE);
};

const resume = () => setTimeout(() => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    const released = [...pending].some(id => {
        const block = document.getElementById(id);

        return !block || !held(block);
    });

    if (stale || released) {
        stale = false;
        schedule();
    }
});

const renew = channel => {
    const url = new URL(script.dataset.tokenUrl, window.location.href);

    if (channel) {
        url.searchParams.set('channel', channel);
    }

    return fetch(url, {credentials: 'same-origin'}).then(response => {
        if (response.status === 403 || response.redirected) {
            return null;
        }

        return response.ok ? response.json().then(body => body.token) : Promise.reject(new Error(String(response.status)));
    });
};

if (script && blocks().length) {
    const { connect } = await import(script.dataset.client);

    connect({
        endpoint: script.dataset.endpoint,
        token: script.dataset.token,
        channels: JSON.parse(script.dataset.channels),
        renew,
    }, schedule);

    document.addEventListener('visibilitychange', resume);
    document.addEventListener('focusout', resume);
}
