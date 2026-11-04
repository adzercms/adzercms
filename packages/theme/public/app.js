import 'adzercms/dialogs';
import htmx from 'htmx.org';
import Sortable from 'sortablejs';

const openPane = tab => {
    const scope = tab.closest('[data-panes]');
    scope.querySelectorAll('[data-pane]').forEach(button => button.classList.toggle('active', button === tab));
    scope.querySelectorAll('[data-pane-body]').forEach(pane => {
        pane.classList.toggle('active', pane.dataset.paneBody === tab.dataset.pane);
    });
};

document.addEventListener('click', event => {
    const preset = event.target.closest('.amounts button[data-amount]');
    if (preset) {
        const form = preset.closest('form');
        form.querySelector('input[name="amount"]').value = preset.dataset.amount;
        form.querySelectorAll('.amounts button').forEach(button => button.classList.toggle('active', button === preset));
        return;
    }

    const tab = event.target.closest('[data-pane]');
    if (tab) {
        openPane(tab);
        return;
    }

    const article = event.target.closest('[data-help-insert]');
    if (article) {
        const reply = document.querySelector('.chat-reply textarea[name="body"]');
        const at = reply.selectionStart;
        const before = reply.value.slice(0, at);
        reply.setRangeText(`${before && !before.endsWith('\n') ? '\n' : ''}${article.dataset.helpInsert}\n`, at, reply.selectionEnd, 'end');
        reply.focus();
        return;
    }

    const copier = event.target.closest('[data-copy]');
    if (copier) {
        const source = document.querySelector(copier.dataset.copy);
        navigator.clipboard.writeText(source.value ?? source.textContent).then(() => {
            copier.textContent = copier.dataset.copied;
        });
        return;
    }

    if (event.target.closest('[data-print]')) {
        window.print();
        return;
    }

    const readonlyArea = event.target.closest('textarea[readonly]');
    if (readonlyArea) {
        readonlyArea.select();
        return;
    }

    const opener = event.target.closest('[data-modal], [data-record]');
    if (opener && (opener.tagName !== 'TR' || !event.target.closest('a, button, input, select, form, [data-order-id]'))) {
        if (opener.dataset.record) {
            htmx.ajax('GET', opener.dataset.record, {target: '#record'});
        } else {
            document.getElementById(opener.dataset.modal).showModal();
        }
        return;
    }

    const row = event.target.closest('[data-href]');
    if (row && !event.target.closest('a, button, input, select, form, [data-order-id]')) {
        location.assign(row.dataset.href);
    }
});

const TIP_GAP = 6;

const placeTip = event => {
    const tip = event.target.closest?.('.tip');
    if (!tip) {
        return;
    }

    const text = tip.querySelector('.tip-text');
    const spot = tip.getBoundingClientRect();
    const box = text.getBoundingClientRect();
    const above = spot.top - box.height - TIP_GAP;

    text.style.left = `${Math.min(Math.max(spot.left + (spot.width - box.width) / 2, TIP_GAP), innerWidth - box.width - TIP_GAP)}px`;
    text.style.top = `${above < TIP_GAP ? spot.bottom + TIP_GAP : above}px`;
};

document.addEventListener('mouseover', placeTip);
document.addEventListener('focusin', placeTip);

const follows = (follower, source) => (follower.dataset.own === undefined
    ? follower.defaultValue === source.defaultValue
    : follower.dataset.own === '');

document.addEventListener('input', event => {
    const field = event.target;
    const form = field.form;
    if (!form) {
        return;
    }

    if (field.dataset.mirror) {
        field.dataset.own = field.value === form.elements[field.dataset.mirror]?.value ? '' : '1';
        return;
    }

    form.querySelectorAll(`[data-mirror="${CSS.escape(field.name)}"]`).forEach(follower => {
        if (follows(follower, field)) {
            follower.value = field.value;
        }
    });
});

document.addEventListener('input', event => {
    const field = event.target;
    field.form?.querySelectorAll(`a[data-from="${CSS.escape(field.name)}"]`).forEach(link => {
        link.href = link.dataset.url.replace('{}', encodeURIComponent(field.value));
    });
});

document.addEventListener('input', event => {
    if (!event.target.matches('.pay input[name="amount"]')) {
        return;
    }

    event.target.form.querySelectorAll('.amounts button').forEach(button => button.classList.remove('active'));
});

const conditionals = scope => {
    scope.querySelectorAll('[data-when]').forEach(node => {
        const fields = node.closest('form')?.elements;

        node.hidden = !node.dataset.when.split('&').every(condition => {
            const [name, values] = condition.split('=');

            return values.split('|').includes(fields?.[name]?.value ?? '');
        });
    });
};

const STEP = '[data-step]';
const stepsOf = form => [...form.querySelectorAll(STEP)].filter(step => !step.hidden);

const showStep = (form, key) => {
    const steps = stepsOf(form);
    const at = Math.max(0, steps.findIndex(step => step.dataset.step === key));
    const last = steps.length - 1;
    if (!steps.length) {
        return;
    }

    form.dataset.stepAt = steps[at].dataset.step;
    form.querySelectorAll(STEP).forEach(step => step.classList.toggle('current', step === steps[at]));
    const nav = form.querySelector(':scope > .steps-nav') ?? form.insertAdjacentElement('afterbegin',
        Object.assign(document.createElement('ol'), {className: 'steps-nav'}));
    nav.replaceChildren(...steps.map((step, index) => {
        const item = Object.assign(document.createElement('li'), {textContent: step.dataset.stepTitle});
        item.classList.toggle('done', index < at);
        item.classList.toggle('current', index === at);
        if (index < at) {
            item.dataset.stepGo = step.dataset.step;
        }
        return item;
    }));
    form.querySelectorAll('[data-step-back]').forEach(button => button.hidden = at === 0);
    form.querySelectorAll('[data-step-next]').forEach(button => button.hidden = at === last);
    form.querySelectorAll('[data-step-submit]').forEach(button => button.hidden = at !== last);
    saveDraft(form);
};

const moveStep = (form, shift) => {
    const steps = stepsOf(form);
    const at = steps.findIndex(step => step.dataset.step === form.dataset.stepAt);
    const fields = shift > 0 ? [...steps[at].querySelectorAll('input, select, textarea')] : [];

    if (fields.every(field => field.disabled || field.closest('[hidden]') || field.reportValidity())) {
        goStep(form, steps[Math.min(Math.max(at + shift, 0), steps.length - 1)].dataset.step);
    }
};

const goStep = (form, key) => {
    showStep(form, key);
    const nav = form.querySelector(':scope > .steps-nav');
    if (nav.getBoundingClientRect().top < parseFloat(getComputedStyle(nav).scrollMarginTop)) {
        nav.scrollIntoView({block: 'start'});
    }
};

const UNDRAFTED = ['hidden', 'file', 'password', 'submit', 'button', 'reset'];
const draftKey = form => `steps:${new URL(form.action, location.href).pathname}`;
const drafted = form => [...form.elements].filter(field => field.name && !UNDRAFTED.includes(field.type));
const checkable = field => ['checkbox', 'radio'].includes(field.type);

const saveDraft = form => sessionStorage.setItem(draftKey(form), JSON.stringify({
    step: form.dataset.stepAt,
    fields: drafted(form).map(field => [field.name, checkable(field) ? (field.checked ? field.value : null) : field.value]),
}));

const draftOf = form => {
    try {
        return JSON.parse(sessionStorage.getItem(draftKey(form)) ?? 'null');
    } catch {
        return null;
    }
};

const restoreDraft = form => {
    const draft = draftOf(form);
    if (!draft) {
        return null;
    }
    const values = new Map();
    draft.fields.forEach(([name, value]) => values.set(name, [...(values.get(name) ?? []), value]));
    drafted(form).forEach(field => {
        const saved = values.get(field.name);
        if (!saved) {
            return;
        }
        if (checkable(field)) {
            field.checked = saved.includes(field.value);
        } else if (saved.length) {
            field.value = saved.shift();
        }
        if (field.dataset.mirror) {
            field.dataset.own = field.value === form.elements[field.dataset.mirror]?.value ? '' : '1';
        }
    });

    return draft.step;
};

const steppers = scope => [scope, ...scope.querySelectorAll('form[data-steps]')]
    .filter(node => node.matches?.('form[data-steps]'))
    .forEach(form => {
        const step = form.dataset.stepAt ? null : restoreDraft(form);
        conditionals(form);
        showStep(form, form.dataset.stepAt ?? form.dataset.stepOpen ?? step);
    });

document.addEventListener('input', event => {
    const form = event.target.form;
    if (form?.matches('[data-steps]')) {
        saveDraft(form);
    }
});

document.addEventListener('submit', event => {
    if (event.target.matches('form[data-steps]') && !event.defaultPrevented) {
        sessionStorage.removeItem(draftKey(event.target));
    }
});

document.addEventListener('click', event => {
    const control = event.target.closest('[data-step-next], [data-step-back], [data-step-go]');
    const form = control?.closest('form[data-steps]');
    if (!form) {
        return;
    }

    if (control.dataset.stepGo !== undefined) {
        goStep(form, control.dataset.stepGo);
    } else {
        moveStep(form, control.dataset.stepNext !== undefined ? 1 : -1);
    }
});

const midway = form => form?.matches('[data-steps]') && stepsOf(form).at(-1)?.dataset.step !== form.dataset.stepAt;

document.addEventListener('keydown', event => {
    if (event.key === 'Enter' && event.target.matches('input') && midway(event.target.form)) {
        event.preventDefault();
        moveStep(event.target.form, 1);
    }
});

document.addEventListener('submit', event => {
    if (midway(event.target)) {
        event.preventDefault();
        event.stopImmediatePropagation();
        moveStep(event.target, 1);
    }
}, true);

let switched = null;

document.addEventListener('change', event => {
    if (event.target.matches('[data-submit]')) {
        switched = event.target;
        event.target.form.requestSubmit();
        switched = null;
        return;
    }

    const form = event.target.closest('form');
    const preset = event.target.selectedOptions?.[0]?.dataset.sets;
    if (form && preset) {
        const [name, value] = preset.split('=');
        if (form.elements[name]) {
            form.elements[name].value = value;
        }
    }
    if (form) {
        conditionals(form);
        if (form.matches('[data-steps]')) {
            showStep(form, form.dataset.stepAt);
        }
    }
});

const TOAST_LIFE = 6000;
const TOAST_FADE = 200;

document.addEventListener('DOMContentLoaded', () => {
    const hashed = location.hash.length > 1 && document.querySelector(`[data-pane="${CSS.escape(location.hash.slice(1))}"]`);
    if (hashed) {
        openPane(hashed);
    }
    conditionals(document);
    steppers(document);
    document.querySelector('dialog[data-open]')?.showModal();
    const toasts = document.querySelector('.toasts');
    if (toasts?.children.length) {
        toasts.showPopover?.();
    }
    document.querySelectorAll('.toast').forEach(toast => setTimeout(() => {
        toast.classList.add('leaving');
        setTimeout(() => toast.remove(), TOAST_FADE);
    }, TOAST_LIFE));
});

const running = show => {
    const present = document.querySelector('.running-bar.page');
    if (show && !present) {
        document.body.prepend(Object.assign(document.createElement('div'), {className: 'running-bar page'}));
    }
    if (!show) {
        present?.remove();
    }
};

const polling = element => (element?.getAttribute('hx-trigger') ?? '').includes('every');

let approved = null;
let sending = new WeakSet();

const question = (form, submitter) => submitter?.dataset.confirm
    ?? form.querySelector('option:checked[data-confirm]')?.dataset.confirm
    ?? form.dataset.confirm;

const ask = (text, agreed, declined = () => {}) => {
    const dialog = document.getElementById('confirm');

    dialog.querySelector('[data-confirm-text]').textContent = text;
    dialog.returnValue = '';
    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'yes') {
            agreed();
        } else {
            declined();
        }
    }, {once: true});
    dialog.showModal();
};

document.addEventListener('submit', event => {
    if (event.submitter && sending.has(event.target)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);
document.addEventListener('submit', event => {
    const form = event.target;
    const submitter = event.submitter ?? null;
    const asked = question(form, submitter);

    if (asked && approved !== form) {
        const toggled = switched;
        event.preventDefault();
        ask(asked, () => {
            approved = form;
            form.requestSubmit(submitter);
        }, () => {
            if (toggled) {
                toggled.checked = !toggled.checked;
            }
        });

        return;
    }

    approved = null;
    if ((submitter?.formMethod || form.method) !== 'dialog') {
        sending.add(form);
        running(true);
    }
});
document.addEventListener('htmx:confirm', event => {
    const asked = event.detail.elt?.dataset?.confirm;

    if (asked) {
        event.preventDefault();
        ask(asked, () => event.detail.issueRequest(true));
    }
});
document.addEventListener('htmx:beforeRequest', event => running(!polling(event.detail.elt)));
document.addEventListener('htmx:afterRequest', event => {
    sending.delete(event.detail.elt);
    running(false);
});
window.addEventListener('pageshow', () => {
    sending = new WeakSet();
    running(false);
});

document.addEventListener('htmx:beforeSwap', event => {
    if (event.detail.target && event.detail.target.id === 'record') {
        event.detail.swapOverride = 'innerHTML';
    }
});

document.addEventListener('htmx:load', event => {
    conditionals(event.target);
    steppers(event.target);
});

const ORDER_FIELD = 'ids[]';
const ORDER_ANIMATION = 150;

const groupOf = row => row.querySelector('[data-order-id]').dataset.orderGroup;

const arrange = scope => scope.querySelectorAll('tbody[data-order]').forEach(body => {
    if (Sortable.get(body)) {
        return;
    }

    Sortable.create(body, {
        handle: '[data-order-id]',
        animation: ORDER_ANIMATION,
        onMove: event => groupOf(event.related) === groupOf(event.dragged),
        onEnd: event => {
            if (event.oldIndex === event.newIndex) {
                return;
            }

            const form = document.getElementById(body.dataset.order);
            const group = groupOf(event.item);
            form.querySelectorAll(`input[name="${ORDER_FIELD}"]`).forEach(input => input.remove());
            body.querySelectorAll(`[data-order-group="${CSS.escape(group)}"]`).forEach(handle => {
                form.append(Object.assign(document.createElement('input'), {type: 'hidden', name: ORDER_FIELD, value: handle.dataset.orderId}));
            });
            form.requestSubmit();
        },
    });
});

document.addEventListener('DOMContentLoaded', () => arrange(document));
document.addEventListener('htmx:load', event => arrange(event.target));

document.addEventListener('htmx:afterSwap', event => {
    if (event.target.id !== 'record') {
        return;
    }

    if (!event.target.open) {
        event.target.showModal();
    }

    const field = event.target.querySelector('input:not([type="hidden"]), select, textarea');
    (field ?? event.target).focus();
});

const UPLOAD_KEY = '_uploads[]';
const uploads = new Map();
const waiting = new Map();
let opener = null;

const busy = form => [...uploads.keys()].some(input => input.form === form);

const mirror = form => {
    const entry = waiting.get(form);
    if (entry?.bar) {
        const values = [...uploads.keys()].filter(input => input.form === form).map(input => input.parentElement.querySelector('.file-progress').value);
        entry.bar.value = Math.min(100, ...values);
    }
};

const send = form => {
    const entry = waiting.get(form);
    waiting.delete(form);
    entry.row?.remove();
    if (form.checkValidity()) {
        form.addEventListener('htmx:afterRequest', () => entry.holder?.remove(), {once: true});
        form.requestSubmit(entry.submitter);

        return;
    }

    running(false);
    if (entry.holder) {
        const record = document.getElementById('record');
        record.replaceChildren(...entry.holder.childNodes);
        entry.holder.remove();
        record.showModal();
    }
    form.reportValidity();
};

const settle = () => {
    for (const form of waiting.keys()) {
        if (!busy(form)) {
            send(form);
        }
    }
};

const pendingRow = form => {
    const scope = opener?.closest('main') ?? document.querySelector('main') ?? document.body;
    const label = form.querySelector('input[type="text"]')?.value
        || [...form.querySelectorAll('.file-names')].map(names => names.textContent).filter(Boolean).join(', ');
    const bar = Object.assign(document.createElement('progress'), {max: 100, value: 0});
    const body = scope.querySelector('table tbody');
    const row = document.createElement(body ? 'tr' : 'p');
    const place = body ? row.appendChild(Object.assign(document.createElement('td'), {colSpan: body.closest('table').tHead?.rows[0]?.cells.length ?? 1})) : row;
    place.append(Object.assign(document.createElement('strong'), {textContent: label}), bar);
    row.className = 'pending-row';
    if (body) {
        body.prepend(row);
    } else {
        const empty = scope.querySelector('.empty-state');
        empty ? empty.before(row) : scope.prepend(row);
    }

    return {row, bar};
};

const fail = input => {
    input.setCustomValidity(input.parentElement.dataset.failed);
    if (!waiting.has(input.form)) {
        input.reportValidity();
    }
};

const pick = input => {
    const field = input.parentElement;
    const progress = field.querySelector('.file-progress');
    const files = [...input.files];

    field.querySelector('.file-names').textContent = files.map(file => file.name).join(', ');
    input.dataset.name ??= input.name;
    input.removeAttribute('name');
    input.setCustomValidity('');
    field.querySelectorAll(`input[name="${UPLOAD_KEY}"]`).forEach(key => key.remove());
    uploads.get(input)?.forEach(request => request.abort());
    uploads.delete(input);
    progress.value = 0;
    progress.hidden = files.length === 0;
    if (files.length === 0) {
        settle();

        return;
    }

    const total = files.reduce((sum, file) => sum + file.size, 0) || 1;
    const loaded = files.map(() => 0);
    let left = files.length;
    const requests = files.map((file, index) => {
        const request = new XMLHttpRequest();
        const body = new FormData();
        body.append('_token', input.form.elements._token?.value ?? '');
        body.append('field', input.dataset.name);
        body.append('file', file);
        request.responseType = 'json';
        request.upload.addEventListener('progress', event => {
            loaded[index] = event.loaded;
            progress.value = Math.round(loaded.reduce((sum, part) => sum + part, 0) * 100 / total);
            mirror(input.form);
        });
        request.addEventListener('load', () => {
            if (request.status === 200 && request.response?.key) {
                field.append(Object.assign(document.createElement('input'), {type: 'hidden', name: UPLOAD_KEY, value: request.response.key}));
            } else {
                fail(input);
            }
        });
        request.addEventListener('error', () => fail(input));
        request.addEventListener('loadend', () => {
            left -= 1;
            if (left === 0 && uploads.get(input) === requests) {
                uploads.delete(input);
                progress.hidden = true;
                settle();
            }
        });
        request.open('POST', field.dataset.upload);
        request.send(body);

        return request;
    });
    uploads.set(input, requests);
};

document.addEventListener('change', event => {
    if (event.target.matches('.file-field > input[type="file"]')) {
        pick(event.target);
    }
});

document.addEventListener('submit', event => {
    const form = event.target;
    if (!busy(form) || (question(form, event.submitter) && approved !== form)) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    if (waiting.has(form)) {
        return;
    }

    const record = form.closest('#record');
    if (!record) {
        waiting.set(form, {submitter: event.submitter});
        running(true);

        return;
    }

    const holder = Object.assign(document.createElement('div'), {hidden: true});
    document.body.append(holder);
    holder.append(...record.childNodes);
    record.close();
    waiting.set(form, {submitter: event.submitter, holder, ...pendingRow(form)});
    mirror(form);
}, true);

document.addEventListener('close', event => {
    for (const [input, requests] of uploads) {
        if (event.target.contains(input)) {
            requests.forEach(request => request.abort());
            uploads.delete(input);
        }
    }
}, true);

document.addEventListener('htmx:beforeRequest', event => {
    if (event.detail.target?.id === 'record' && !event.detail.elt.closest('#record')) {
        opener = event.detail.elt;
    }
});

window.addEventListener('beforeunload', event => {
    if (waiting.size > 0 || [...uploads.keys()].some(input => input.isConnected)) {
        event.preventDefault();
    }
});

document.addEventListener('change', event => {
    const toggle = event.target.closest('.chart-toggle input[data-line]');
    if (toggle) {
        document.getElementById(toggle.dataset.line)?.classList.toggle('is-off', !toggle.checked);
    }
});

document.addEventListener('input', event => {
    const palette = event.target.closest('.palette');
    if (!palette) {
        return;
    }

    if (event.target.matches('[data-palette-own]')) {
        const own = event.target.previousElementSibling;
        own.value = event.target.value;
        own.checked = true;
    }

    const picked = palette.querySelector('input[type="radio"]:checked');
    document.documentElement.style.setProperty('--brand', picked.dataset.color ?? picked.value);
});
