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
const FRESH = 'block-new-';
const PICTURE = 'image';
const FILES = 'files';

const root = document.querySelector('[data-builder]');
const blocks = JSON.parse(root.dataset.blocks);
const screens = JSON.parse(root.dataset.screens);
const kinds = JSON.parse(root.dataset.types);
const sectors = JSON.parse(root.dataset.style);
const anchors = new Set(blocks.map(block => block.anchor));
const record = document.getElementById('record');
const panel = root.querySelector('[data-pictures-panel]');
const refusal = panel.querySelector('[data-pictures-refusal]');
let version = root.dataset.version;
let saved = '';
let agreed = null;
let leaving = false;
let editing = null;
let picking = null;
let fresh = 0;

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

const media = screen => (screen.media ? `(max-width: ${screen.media}px)` : '');

const reason = error => {
    try {
        return JSON.parse(error).message ?? root.dataset.failed;
    } catch {
        return root.dataset.failed;
    }
};

const field = ({property, label, kind, keywords, units, signed}) => ({
    property,
    label,
    ...{
        color: {type: 'color', colorPicker: {showAlpha: false}},
        choice: {type: 'select', options: keywords},
        fraction: {type: 'slider', min: 0, max: 1, step: 0.01},
        length: {type: 'number', units, fixedValues: keywords.map(({id}) => id), ...(signed ? {} : {min: 0})},
    }[kind],
});

const fill = (component, entry = component.get('block'), at = null) => {
    const first = component ? placed()[0] === component : !container().components().some((one, index) => index < at && one.get('type') === BLOCK);
    editing = {component, entry, at};
    htmx.ajax('POST', entry.dialog ?? entry.draw, {target: '#record', values: {[TOKEN]: token(), stored: JSON.stringify(component ? entry.values : {}), first: first ? '1' : ''}});
};

const filled = ({values, anchor, html}) => {
    const {component, entry, at} = editing;
    const element = new DOMParser().parseFromString(html, 'text/html').getElementById(anchor);
    const block = component ? {...entry, values} : {...entry, values, style: {}, id: null, anchor: `${FRESH}${++fresh}`};
    const shown = {attributes: {...attributes(element), id: block.anchor}, content: element.innerHTML, block};
    editing = null;
    record.close();
    if (component) {
        component.set(shown);
    } else {
        editor.select(container().append({type: BLOCK, tagName: element.localName, name: block.label, ...shown}, {at})[0]);
    }
};

const setup = editor => {
    const types = editor.DomComponents;
    types.addType(PART, {model: {defaults: LOCKED}});
    types.addType(BLOCKS, {model: {defaults: {...LOCKED, layerable: true, droppable: source => source.get('type') === BLOCK}}});
    types.addType(BLOCK, {model: {
        defaults: {draggable: (source, target) => target.get('type') === BLOCKS, droppable: false, copyable: false, editable: false},
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

    editor.AssetManager.addType(PICTURE, {view: {template() {
        return `<div class="${this.pfx}preview-cont">${this.getPreview()}</div>`;
    }}});
    editor.on('modal', ({open, content}) => {
        if (!open) {
            panel.close();
        } else if (!panel.open) {
            panel.querySelector('[data-pictures-body]').replaceChildren(content);
            refusal.hidden = true;
            panel.showModal();
        }
    });
    editor.on('asset:upload:start', () => {
        refusal.hidden = true;
    });
    editor.on('asset:upload:error', error => {
        refusal.textContent = reason(error);
        refusal.hidden = false;
    });

    editor.on('block:drag:start', () => editor.UndoManager.stop());
    editor.on('block:drag:stop', component => {
        if (component) {
            const at = component.index();
            const entry = component.get('block');
            component.remove();
            fill(null, entry, at);
        }
        editor.UndoManager.start();
    });

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
        editor.UndoManager.skip(() => {
            for (const component of placed()) {
                const own = component.get('block').style[''] ?? {};
                for (const screen of screens.filter(one => own[one.id])) {
                    editor.Css.setIdRule(component.getId(), own[screen.id], {mediaText: media(screen)});
                }
            }
        });
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
    styleManager: {appendTo: root.querySelector('[data-style-panel]'), sectors: sectors.map(({id, name, properties}) => ({id, name, open: false, properties: properties.map(field)}))},
    blockManager: {appendTo: root.querySelector('[data-palette]'), blocks: kinds.map(kind => ({id: kind.type, label: kind.label, content: {type: BLOCK, block: kind}}))},
    assetManager: {assets: JSON.parse(root.dataset.pictures), upload: root.dataset.upload, uploadName: FILES, params: {[TOKEN]: token()}, showUrlInput: false},
    modal: {custom: true},
    selectorManager: {componentFirst: true},
    i18n: {locale: document.documentElement.lang, detectLocale: false, localeFallback: 'en', messages: {ru}},
    plugins: [setup],
    components: [...page.body.childNodes].map(part).filter(Boolean),
});

const container = () => editor.getWrapper().findType(BLOCKS)[0];
const placed = () => container().components().filter(component => component.get('type') === BLOCK);
const styleOf = component => {
    const own = Object.fromEntries(screens
        .map(screen => [screen.id, Object.entries(editor.Css.getIdRule(component.getId(), {mediaText: media(screen)})?.getStyle() ?? {}).filter(([, value]) => value !== '')])
        .filter(([, properties]) => properties.length > 0)
        .map(([screen, properties]) => [screen, sorted(Object.fromEntries(properties))]));
    const rest = Object.entries(component.get('block').style).filter(([target]) => target !== '');

    return sorted(Object.fromEntries(Object.keys(own).length > 0 ? [...rest, ['', sorted(own)]] : rest));
};
const documentOf = () => ({version, blocks: placed().map(component => {
    const {id, type, values} = component.get('block');

    return {id, type, values: sorted(values), style: styleOf(component)};
})});
const dirty = () => JSON.stringify(documentOf()) !== saved;

const status = (message, failed) => {
    const line = root.querySelector('[data-status]');
    line.textContent = message;
    line.className = `builder-status flash ${failed ? 'flash-error' : 'flash-success'}`;
};

const save = async button => {
    const sent = placed();
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
            answer.blocks.forEach(({id, anchor}, at) => {
                const block = sent[at].get('block');
                if (block.id === null) {
                    sent[at].set('block', {...block, id, anchor});
                    sent[at].addAttributes({id: anchor});
                }
            });
            editor.UndoManager.clear();
            saved = JSON.stringify(documentOf());
        }
    } catch {
        status(root.dataset.failed, true);
    } finally {
        button.disabled = false;
    }
};

const chosen = asset => {
    picking.querySelector('input[type="hidden"]').value = asset.get('name');
    const preview = picking.querySelector('.picture-preview');
    preview.querySelector('img').src = asset.getSrc();
    preview.hidden = false;
    editor.AssetManager.close();
};

document.addEventListener('click', event => {
    const pick = event.target.closest('[data-picture-pick]');
    if (pick) {
        picking = pick.closest('[data-picture]');
        editor.AssetManager.open({accept: root.dataset.accept, select: chosen});
    }
});

panel.addEventListener('close', () => editor.Modal.isOpen() && editor.Modal.close());

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
