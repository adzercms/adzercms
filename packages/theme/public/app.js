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
    if (!event.target.matches('.pay input[name="amount"]')) {
        return;
    }

    event.target.form.querySelectorAll('.amounts button').forEach(button => button.classList.remove('active'));
});

const conditionals = scope => {
    scope.querySelectorAll('[data-when]').forEach(node => {
        const [name, values] = node.dataset.when.split('=');
        const field = node.closest('form')?.elements[name];

        node.hidden = !values.split('|').includes(field?.value ?? '');
    });
};

document.addEventListener('change', event => {
    if (event.target.matches('[data-submit]')) {
        event.target.form.requestSubmit();
        return;
    }

    const form = event.target.closest('form');
    if (form) {
        conditionals(form);
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

const ask = (question, agreed) => {
    const dialog = document.getElementById('confirm');

    dialog.querySelector('[data-confirm-text]').textContent = question;
    dialog.returnValue = '';
    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'yes') {
            agreed();
        }
    }, {once: true});
    dialog.showModal();
};

document.addEventListener('submit', event => {
    const form = event.target;
    const submitter = event.submitter ?? null;
    const question = submitter?.dataset.confirm ?? form.dataset.confirm;

    if (question && approved !== form) {
        event.preventDefault();
        ask(question, () => {
            approved = form;
            form.requestSubmit(submitter);
        });

        return;
    }

    approved = null;
    running(true);
    form.querySelectorAll('button[type="submit"]').forEach(button => {
        setTimeout(() => { button.disabled = true; }, 0);
    });
});
document.addEventListener('htmx:confirm', event => {
    const question = event.detail.elt?.dataset?.confirm;

    if (question) {
        event.preventDefault();
        ask(question, () => event.detail.issueRequest(true));
    }
});
document.addEventListener('htmx:beforeRequest', event => running(!polling(event.detail.elt)));
document.addEventListener('htmx:afterRequest', () => running(false));
window.addEventListener('pageshow', () => running(false));

document.addEventListener('htmx:beforeSwap', event => {
    if (event.detail.target && event.detail.target.id === 'record') {
        event.detail.swapOverride = 'innerHTML';
    }
});

document.addEventListener('htmx:load', event => conditionals(event.target));

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

document.addEventListener('change', event => {
    const toggle = event.target.closest('.chart-toggle input[data-line]');
    if (toggle) {
        document.getElementById(toggle.dataset.line)?.classList.toggle('is-off', !toggle.checked);
    }
});
