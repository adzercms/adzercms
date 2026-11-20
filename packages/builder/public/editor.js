import grapesjs from 'grapesjs';
import ru from 'grapesjs/locale/ru';
import htmx from 'htmx.org';

const PART = 'frame-part';
const BLOCKS = 'page-blocks';
const BLOCK = 'ready-block';
const SKIPPED = 'script, template, noscript';
const SHEETS = 'link[rel="stylesheet"]';
const HEAD = 'link[rel="preload"], style';
const LOCKED = {selectable: false, hoverable: false, highlightable: false, draggable: false, droppable: false, removable: false, copyable: false, layerable: false, editable: false, stylable: false, badgable: false};
const UNAVAILABLE = 'builder-unavailable';
const TOKEN = '_token';
const DELETE = 'core:component-delete';
const VALUES = 'builder-values';
const JSON_TYPE = 'application/json';

const root = document.querySelector('[data-builder]');
const blocks = JSON.parse(root.dataset.blocks);
const screens = JSON.parse(root.dataset.screens);
const anchors = new Set(blocks.map(block => block.anchor));
const record = document.getElementById('record');
let version = root.dataset.version;
let saved = '';
let agreed = null;
let leaving = false;
let editing = null;

const page = new DOMParser().parseFromString(await (await fetch(root.dataset.frame)).text(), 'text/html');
const end = page.querySelector('[data-builder-end]');
const holder = end.parentElement;

const attributes = element => Object.fromEntries([...element.attributes].map(({name, value}) => [name, value]));

const absent = block => {
    const note = document.createElement('p');
    note.textContent = `${block.label}: ${root.dataset.unavailable}`;

    return {type: BLOCK, tagName: 'section', attributes: {id: block.anchor, class: UNAVAILABLE}, content: note.outerHTML, name: block.label, block};
};

const ready = block => {
    const element = page.getElementById(block.anchor);

    return element
        ? {type: BLOCK, tagName: element.localName, attributes: attributes(element), content: element.innerHTML, name: block.label, block}
        : absent(block);
};

const part = node => {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.textContent.trim() === '' ? null : {type: 'textnode', content: node.textContent};
    }
    if (node.nodeType !== Node.ELEMENT_NODE || node.matches(SKIPPED) || anchors.has(node.id)) {
        return null;
    }

    const own = {tagName: node.localName, attributes: attributes(node)};
    if (node === holder) {
        const children = [...node.childNodes];
        const at = children.indexOf(end);

        return {...own, type: BLOCKS, name: root.dataset.title, components: [...children.slice(0, at).map(part), ...blocks.map(ready), ...children.slice(at + 1).map(part)].filter(Boolean)};
    }

    return node.contains(holder)
        ? {...own, type: PART, components: [...node.childNodes].map(part).filter(Boolean)}
        : {...own, type: PART, content: node.innerHTML};
};

const ask = (question, action) => {
    const form = root.querySelector('[data-ask]');
    agreed = action;
    form.dataset.confirm = question;
    form.requestSubmit();
};

const token = () => root.querySelector(`[name="${TOKEN}"]`).value;

const sorted = values => Object.fromEntries(Object.entries(values).sort(([one], [other]) => (one < other ? -1 : 1)));

const fill = component => {
    const {dialog, values} = component.get('block');
    editing = component;
    htmx.ajax('POST', dialog, {target: '#record', values: {[TOKEN]: token(), stored: JSON.stringify(values), first: placed()[0] === component ? '1' : ''}});
};

const filled = ({values, html}) => {
    const block = editing.get('block');
    const element = new DOMParser().parseFromString(html, 'text/html').getElementById(block.anchor);
    editing.set({attributes: attributes(element), content: element.innerHTML, block: {...block, values}});
    editing = null;
    record.close();
};

const setup = editor => {
    const types = editor.DomComponents;
    types.addType(PART, {model: {defaults: LOCKED}});
    types.addType(BLOCKS, {model: {defaults: {...LOCKED, layerable: true, droppable: `[data-gjs-type="${BLOCK}"]`}}});
    types.addType(BLOCK, {model: {
        defaults: {draggable: `[data-gjs-type="${BLOCKS}"]`, droppable: false, copyable: false, editable: false, stylable: false},
        init() {
            if (this.get('block').dialog) {
                const label = root.querySelector('[data-edit-icon]').innerHTML;
                this.set('toolbar', [{label, attributes: {title: root.dataset.edit}, command: VALUES}, ...this.get('toolbar')]);
            }
        },
    }});

    editor.Commands.add('tlb-delete', {run: ed => {
        const component = ed.getSelected();
        ask(component.get('block').question, () => component.remove());
    }});
    editor.Commands.add(VALUES, {run: ed => fill(ed.getSelected())});

    editor.on('canvas:frame:load:body', ({window: frame}) => {
        for (const {name, value} of page.documentElement.attributes) {
            frame.document.documentElement.setAttribute(name, value);
        }
        frame.document.head.append(...[...page.head.querySelectorAll(HEAD)].map(node => frame.document.importNode(node, true)));
    });

    editor.onReady(() => {
        const wrapper = editor.getWrapper();
        wrapper.set({selectable: false, hoverable: false, droppable: false});
        wrapper.addClass([...page.body.classList]);
        editor.Keymaps.remove(DELETE);
        saved = JSON.stringify(documentOf());
    });
};

const editor = grapesjs.init({
    container: root.querySelector('[data-canvas]'),
    height: '100%',
    width: 'auto',
    storageManager: false,
    telemetry: false,
    cssIcons: '',
    panels: {defaults: []},
    protectedCss: '',
    canvas: {styles: [...page.head.querySelectorAll(SHEETS)].map(link => link.href)},
    canvasCss: `.${UNAVAILABLE}{padding:1rem;border:1px dashed;opacity:.6}`,
    deviceManager: {devices: screens.map(({id, media, width}) => ({id, name: id, width: width ? `${width}px` : '', widthMedia: media ? `${media}px` : ''}))},
    mediaCondition: 'max-width',
    layerManager: {appendTo: root.querySelector('[data-layers]'), root: `[data-gjs-type="${BLOCKS}"]`},
    selectorManager: {componentFirst: true},
    i18n: {locale: document.documentElement.lang, detectLocale: false, localeFallback: 'en', messages: {ru}},
    plugins: [setup],
    components: [...page.body.childNodes].map(part).filter(Boolean),
});

const placed = () => editor.getWrapper().findType(BLOCKS)[0].components().filter(component => component.get('type') === BLOCK);
const documentOf = () => ({version, blocks: placed().map(component => ({id: component.get('block').id, values: sorted(component.get('block').values)}))});
const dirty = () => JSON.stringify(documentOf()) !== saved;

const status = (message, failed) => {
    const line = root.querySelector('[data-status]');
    line.textContent = message;
    line.className = `builder-status flash ${failed ? 'flash-error' : 'flash-success'}`;
};

const save = async button => {
    const body = new FormData();
    body.set(TOKEN, token());
    body.set('document', JSON.stringify(documentOf()));
    button.disabled = true;
    try {
        const response = await fetch(root.dataset.save, {method: 'POST', body});
        const answer = await response.json();
        status(answer.message, !response.ok);
        if (response.ok) {
            version = answer.version;
            saved = JSON.stringify(documentOf());
        }
    } catch {
        status(root.dataset.failed, true);
    } finally {
        button.disabled = false;
    }
};

document.addEventListener('htmx:afterRequest', ({detail: {xhr, successful}}) => {
    if (editing && successful && xhr.getResponseHeader('Content-Type')?.startsWith(JSON_TYPE)) {
        filled(JSON.parse(xhr.responseText));
    }
});

window.addEventListener('submit', event => {
    if (!event.defaultPrevented && event.target.matches('[data-ask]')) {
        agreed?.();
        agreed = null;
    }
});

window.addEventListener('beforeunload', event => {
    if (!leaving && dirty()) {
        event.preventDefault();
    }
});

root.addEventListener('click', event => {
    const exit = event.target.closest('[data-exit]');
    if (exit && dirty()) {
        event.preventDefault();
        ask(root.dataset.leave, () => {
            leaving = true;
            location.assign(exit.href);
        });
    } else if (exit) {
        leaving = true;
    }
    const saving = event.target.closest('[data-save-document]');
    if (saving) {
        save(saving);
    }
    const screen = event.target.closest('[data-screen]');
    if (screen) {
        editor.setDevice(screen.dataset.screen);
        for (const button of root.querySelectorAll('[data-screen]')) {
            button.setAttribute('aria-pressed', String(button === screen));
        }
    }
    if (event.target.closest('[data-undo]')) {
        editor.UndoManager.undo();
    }
    if (event.target.closest('[data-redo]')) {
        editor.UndoManager.redo();
    }
});
