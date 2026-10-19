const MARK = 'adzercmsDialog';
const stack = [];
let returning = 0;
let leaving = null;
let submitted = false;

const depth = () => history.state?.[MARK] ?? 0;

const closeTo = level => {
    while (stack.length > level) {
        stack.pop().close();
    }
};

const sync = () => {
    if (returning) {
        return;
    }

    if (depth() > stack.length) {
        returning++;
        history.go(stack.length - depth());

        return;
    }

    if (leaving) {
        const [method, url] = leaving;
        leaving = null;
        location[method](url);

        return;
    }

    while (depth() < stack.length) {
        history.pushState({...history.state, [MARK]: depth() + 1}, '');
    }
};

new MutationObserver(records => {
    for (const {target} of records) {
        if (target instanceof HTMLDialogElement && target.matches(':modal') && !stack.includes(target)) {
            stack.push(target);
        }
    }
    sync();
}).observe(document.documentElement, {subtree: true, attributeFilter: ['open']});

window.addEventListener('submit', event => {
    submitted = !event.defaultPrevented && (event.submitter?.formMethod || event.target.method) !== 'dialog';
});

window.addEventListener('close', event => {
    const index = stack.indexOf(event.target);
    if (index < 0) {
        return;
    }

    stack.splice(index, 1);
    submitted = false;
    setTimeout(() => {
        if (!submitted) {
            sync();
        }
    });
}, true);

window.addEventListener('popstate', event => {
    if (returning) {
        returning--;
        event.stopImmediatePropagation();
        sync();

        return;
    }

    if (stack.length <= depth()) {
        return;
    }

    event.stopImmediatePropagation();
    closeTo(depth());
});

window.navigation?.addEventListener('navigate', event => {
    if (!depth() || leaving || !event.cancelable || event.destination.sameDocument || event.formData
        || event.downloadRequest !== null || !['push', 'replace'].includes(event.navigationType)) {
        return;
    }

    event.preventDefault();
    leaving = [event.navigationType === 'replace' ? 'replace' : 'assign', event.destination.url];
    closeTo(0);
    sync();
});

window.addEventListener('pageshow', event => {
    if (event.persisted) {
        closeTo(0);
    }
    sync();
});
