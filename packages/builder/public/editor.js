import grapesjs from 'grapesjs';
import ru from 'grapesjs/locale/ru';
import htmx from 'htmx.org';

const PART = 'frame-part';
const BLOCKS = 'page-blocks';
const BLOCK = 'ready-block';
const BODY = 'free-body';
const TEXT = 'free-text';
const PICTURE = 'free-picture';
const BUTTON = 'free-button';
const LINE = 'free-line';
const BOX = 'free-box';
const ELEMENTS = new Set([TEXT, PICTURE, BUTTON, LINE, BOX]);
const HOLDERS = new Set([BODY, BOX]);
const SKIPPED = 'script, template, noscript';
const SHEETS = 'link[rel="stylesheet"]';
const HEAD = 'link[rel="preload"], style';
const LOCKED = {selectable: false, hoverable: false, highlightable: false, draggable: false, droppable: false, removable: false, copyable: false, layerable: false, editable: false, stylable: false, badgable: false};
const UNAVAILABLE = 'builder-unavailable';
const FREE_BODY = 'site-free-body';
const KEY = 'data-element';
const TOKEN = '_token';
const DELETE = 'core:component-delete';
const VALUES = 'builder-values';
const UP = 'builder-up';
const DOWN = 'builder-down';
const COPY = 'builder-copy';
const ADD = 'builder-add';
const OWN = new Set([VALUES, UP, DOWN, COPY, ADD]);
const KEPT = new Set(['tlb-move', 'tlb-delete']);
const OPEN = 'background';
const JSON_TYPE = 'application/json';
const FRESH = 'block-new-';
const PICTURE_ASSET = 'image';
const FILES = 'files';
const PARAGRAPHS = new Set(['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote']);
const SHADE = 'var(--overlay)';
const LAYERED = /url\("([^"]*)"\)$/;
const COVER = {'background-size': 'cover', 'background-position': 'center', 'background-repeat': 'no-repeat'};

const root = document.querySelector('[data-builder]');
const blocks = JSON.parse(root.dataset.blocks);
const screens = JSON.parse(root.dataset.screens);
const kinds = JSON.parse(root.dataset.types);
const sectors = JSON.parse(root.dataset.style);
const elements = JSON.parse(root.dataset.elements);
const traits = JSON.parse(root.dataset.traits);
const pictures = JSON.parse(root.dataset.pictures);
const free = root.dataset.free;
const loose = kinds.find(kind => kind.type === free);
const actions = JSON.parse(root.dataset.actions);
const icons = Object.fromEntries([...root.querySelectorAll('template[data-icon]')].map(one => [one.dataset.icon, one.innerHTML]));
const library = root.querySelector('[data-library]');
const pictured = new Set(sectors.flatMap(({properties}) => properties.filter(({kind}) => kind === 'picture').map(({property}) => property)));
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
let adding = null;

const page = new DOMParser().parseFromString(await (await fetch(root.dataset.frame)).text(), 'text/html');
const end = page.querySelector('[data-builder-end]');
const holder = end.parentElement;

const attributes = element => Object.fromEntries([...element.attributes].map(({name, value}) => [name, value]));

const absent = block => {
    const note = document.createElement('p');
    note.textContent = `${block.label}: ${root.dataset.unavailable}`;

    return {type: BLOCK, tagName: 'section', attributes: {id: block.anchor, class: UNAVAILABLE}, content: note.outerHTML, name: block.label, block};
};

const markup = paragraphs => paragraphs.map(runs => {
    const line = document.createElement('p');
    for (const run of runs) {
        let node = document.createTextNode(run.text);
        for (const [mark, tag] of [['italic', 'em'], ['bold', 'strong'], ['link', 'a']]) {
            if (run[mark]) {
                const wrap = document.createElement(tag);
                if (mark === 'link') {
                    wrap.setAttribute('href', run.link);
                }
                wrap.append(node);
                node = wrap;
            }
        }
        line.append(node);
    }

    return line.outerHTML;
}).join('');

const source = name => pictures.find(one => one.name === name)?.src;

const layered = src => `linear-gradient(${SHADE},${SHADE}),url("${src}")`;

const element = node => {
    const own = {[KEY]: node.key};

    return {
        text: () => ({type: TEXT, attributes: {...own, class: 'prose'}, components: markup(node.paragraphs)}),
        picture: () => ({type: PICTURE, attributes: {...own, class: 'site-picture', alt: node.alt ?? ''}, ...(source(node.name) ? {src: source(node.name)} : {})}),
        button: () => ({type: BUTTON, attributes: {...own, class: 'button', href: node.link}, components: [{type: 'textnode', content: node.label}]}),
        line: () => ({type: LINE, attributes: own}),
        box: () => ({type: BOX, attributes: own, components: node.items.map(element)}),
    }[node.kind]();
};

const framed = (node, items) => {
    if (node.nodeType === Node.TEXT_NODE) {
        return node.textContent.trim() === '' ? null : {type: 'textnode', content: node.textContent};
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
        return null;
    }
    const own = {tagName: node.localName, attributes: attributes(node)};
    if (node.classList.contains(FREE_BODY)) {
        return {...own, type: BODY, components: items.map(element)};
    }

    return node.querySelector(`.${FREE_BODY}`)
        ? {...own, type: PART, components: [...node.childNodes].map(child => framed(child, items)).filter(Boolean)}
        : {...own, type: PART, content: node.innerHTML};
};

const shape = (drawn, block) => (block.type === free
    ? {attributes: attributes(drawn), components: [...drawn.childNodes].map(node => framed(node, JSON.parse(block.values.elements ?? '[]'))).filter(Boolean)}
    : {attributes: attributes(drawn), content: drawn.innerHTML});

const ready = block => {
    const drawn = page.getElementById(block.anchor);

    return drawn ? {type: BLOCK, tagName: drawn.localName, ...shape(drawn, block), name: block.label, block} : absent(block);
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
    full: true,
    ...{
        color: {type: 'color', colorPicker: {showAlpha: true}},
        choice: {type: 'select', options: keywords},
        picture: {
            type: 'composite',
            properties: [{type: 'file', id: 'src', property: `${property}-src`, label: '', full: true}],
            toStyle: ({src}, {name}) => (src ? {[name]: layered(src), ...COVER} : {[name]: ''}),
            fromStyle: (style, {name}) => ({src: LAYERED.exec(style[name] ?? '')?.[1] ?? ''}),
        },
        fraction: {type: 'slider', min: 0, max: 1, step: 0.01},
        length: {type: 'number', units, fixedValues: keywords.map(({id}) => id), ...(signed ? {} : {min: 0})},
    }[kind],
});

const fill = (component, entry = component.get('block'), at = null, carried = null) => {
    const first = component ? placed()[0] === component : !container().components().some((one, index) => index < at && one.get('type') === BLOCK);
    editing = {component, entry, at, carried};
    htmx.ajax('POST', entry.dialog ?? entry.draw, {target: '#record', values: {[TOKEN]: token(), stored: JSON.stringify(component ? entry.values : {}), first: first ? '1' : ''}});
};

const filled = ({values, anchor, html}) => {
    const {component, entry, at, carried} = editing;
    const drawn = new DOMParser().parseFromString(html, 'text/html').getElementById(anchor);
    const block = component ? {...entry, values} : {...entry, values: carried ? {...values, elements: JSON.stringify([carried])} : values, style: {}, id: null, anchor: `${FRESH}${++fresh}`};
    const shown = {...shape(drawn, block), block};
    shown.attributes.id = block.anchor;
    editing = null;
    record.close();
    if (component) {
        component.set(shown);
    } else {
        const [added] = container().append({type: BLOCK, tagName: drawn.localName, name: block.label, ...shown}, {at});
        editor.select(added);
        within(added).forEach(settle);
    }
};

const settle = component => {
    if (component.get('type') === PICTURE) {
        component.trigger('active');
    }
};

const keyOf = component => component.getAttributes()[KEY];

const within = component => component.components().models.flatMap(child => [...(ELEMENTS.has(child.get('type')) ? [child] : []), ...within(child)]);

const keyed = component => {
    const block = component.closestType(BLOCK);
    const taken = new Set(block ? within(block).filter(other => other !== component).map(keyOf) : []);
    if (keyOf(component) && !taken.has(keyOf(component))) {
        return;
    }
    let number = 1;
    while (taken.has(`e${number}`)) {
        number++;
    }
    component.addAttributes({[KEY]: `e${number}`});
};

const runs = node => {
    const paragraphs = [];
    let line = [];
    const close = () => {
        if (line.length > 0) {
            paragraphs.push(line);
        }
        line = [];
    };
    const walk = (child, marks) => {
        if (child.nodeType === Node.TEXT_NODE) {
            const last = line.at(-1);
            if (child.textContent === '') {
                return;
            }
            if (last && last.bold === marks.bold && last.italic === marks.italic && last.link === marks.link) {
                last.text += child.textContent;
            } else {
                line.push({text: child.textContent, ...marks});
            }

            return;
        }
        if (child.nodeType !== Node.ELEMENT_NODE) {
            return;
        }
        const name = child.localName;
        if (name === 'br') {
            close();

            return;
        }
        const own = {
            bold: marks.bold || name === 'b' || name === 'strong' || undefined,
            italic: marks.italic || name === 'i' || name === 'em' || undefined,
            link: (name === 'a' && child.getAttribute('href')) || marks.link,
        };
        if (PARAGRAPHS.has(name)) {
            close();
        }
        child.childNodes.forEach(next => walk(next, own));
        if (PARAGRAPHS.has(name)) {
            close();
        }
    };
    node.childNodes.forEach(child => walk(child, {}));
    close();

    return paragraphs.map(one => one.map(({text, bold, italic, link}) => ({text, ...(bold ? {bold} : {}), ...(italic ? {italic} : {}), ...(link ? {link} : {})})));
};

const named = src => editor.AssetManager.getAll().find(asset => asset.getSrc() === src)?.get('name') ?? '';

const shown = properties => Object.fromEntries(Object.entries(properties)
    .filter(([property, value]) => !pictured.has(property) || source(value))
    .map(([property, value]) => [property, pictured.has(property) ? layered(source(value)) : value]));

const stored = properties => Object.fromEntries(properties.map(([property, value]) => [property, pictured.has(property) ? named(LAYERED.exec(value)?.[1] ?? '') : value]));

const nodeOf = component => {
    const key = keyOf(component);
    const own = component.getAttributes();

    return {
        [TEXT]: () => ({kind: 'text', key, paragraphs: runs(component.getEl())}),
        [PICTURE]: () => ({kind: 'picture', key, name: named(component.get('src')), alt: own.alt ?? ''}),
        [BUTTON]: () => ({kind: 'button', key, label: component.getEl().textContent.trim(), link: own.href ?? ''}),
        [LINE]: () => ({kind: 'line', key}),
        [BOX]: () => ({kind: 'box', key, items: component.components().map(nodeOf)}),
    }[component.get('type')]();
};

const setup = editor => {
    const types = editor.DomComponents;
    const placing = {draggable: (source, target) => HOLDERS.has(target.get('type')) || (loose && target.get('type') === BLOCKS && !HOLDERS.has(source.parent()?.get('type'))), copyable: false};
    const holding = source => ELEMENTS.has(source.get('type'));
    const never = () => false;
    types.addType(PART, {model: {defaults: LOCKED}});
    types.addType(BLOCKS, {model: {defaults: {...LOCKED, layerable: true, droppable: source => source.get('type') === BLOCK || (loose && ELEMENTS.has(source.get('type')))}}});
    types.addType(BLOCK, {model: {
        defaults: {draggable: (source, target) => target.get('type') === BLOCKS, droppable: false, copyable: false, editable: false, traits: []},
        init() {
            const item = (action, command) => ({label: icons[action], attributes: {title: actions[action], 'data-action': action}, command});
            this.set('toolbar', [
                ...(this.get('block').dialog ? [item('edit', VALUES)] : []),
                item('up', UP),
                item('down', DOWN),
                item('copy', COPY),
                item('add', ADD),
                ...this.get('toolbar').filter(one => KEPT.has(one.command) && !OWN.has(one.command)),
            ]);
        },
    }});
    types.addType(BODY, {model: {defaults: {...LOCKED, highlightable: true, droppable: holding}}});
    types.addType('link', {model: {defaults: {traits: [{name: 'href', label: traits.link}]}}});
    types.addType(TEXT, {extend: 'text', isComponent: never, model: {defaults: {...placing, tagName: 'div', droppable: false, traits: []}}});
    types.addType(PICTURE, {extend: 'image', isComponent: never, model: {defaults: {...placing, traits: [{name: 'alt', label: traits.alt}]}}});
    types.addType(BUTTON, {extend: 'link', isComponent: never, model: {defaults: {...placing, droppable: false, traits: [{name: 'href', label: traits.link}]}}});
    types.addType(LINE, {isComponent: never, model: {defaults: {...placing, tagName: 'hr', void: true, droppable: false, traits: []}}});
    types.addType(BOX, {isComponent: never, model: {defaults: {...placing, tagName: 'div', droppable: holding, traits: []}}});

    editor.Commands.add('tlb-delete', {run: ed => {
        const component = ed.getSelected();
        if (component.get('type') === BLOCK) {
            ask(component.get('block').question, () => component.remove());
        } else {
            component.remove();
        }
    }});
    editor.Commands.add(VALUES, {run: ed => fill(ed.getSelected())});
    editor.Commands.add(UP, {run: ed => {
        const component = ed.getSelected();
        const before = placed()[placed().indexOf(component) - 1];
        if (before) {
            component.move(container(), {at: before.index()});
        }
    }});
    editor.Commands.add(DOWN, {run: ed => {
        const component = ed.getSelected();
        const after = placed()[placed().indexOf(component) + 1];
        if (after) {
            component.move(container(), {at: after.index() + 1});
        }
    }});
    editor.Commands.add(COPY, {run: ed => {
        const component = ed.getSelected();
        const copy = component.clone();
        copy.set('block', {...component.get('block'), id: null, anchor: copy.getId()});
        ed.select(container().append(copy, {at: component.index() + 1})[0]);
    }});
    editor.Commands.add(ADD, {run: ed => {
        adding = ed.getSelected().index() + 1;
        library.showModal();
    }});

    editor.AssetManager.addType(PICTURE_ASSET, {view: {
        template() {
            return `<div class="${this.pfx}preview-cont">${this.getPreview()}</div>`;
        },
        onClick() {
            this.onDblClick();
        },
    }});
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

    editor.on('component:add', component => {
        if (ELEMENTS.has(component.get('type'))) {
            keyed(component);
        }
    });

    const properties = root.querySelector('[data-properties]');
    const design = root.querySelector('[data-design]');
    editor.on('component:toggled', () => {
        properties.hidden = !editor.getSelected()?.getTraits().length;
        design.hidden = !editor.getSelected();
    });

    editor.on('block:drag:start', () => editor.UndoManager.stop());
    editor.on('block:drag:stop', component => {
        const parent = component?.parent();
        const at = component?.index();
        if (component?.get('type') === BLOCK) {
            const entry = component.get('block');
            component.remove();
            editor.UndoManager.start();
            fill(null, entry, at);
        } else if (component && ELEMENTS.has(component.get('type'))) {
            const node = nodeOf(component);
            component.remove();
            editor.UndoManager.start();
            if (parent.get('type') === BLOCKS) {
                fill(null, loose, at, node);
            } else {
                settle(parent.append(element(node), {at})[0]);
            }
        } else {
            editor.UndoManager.start();
        }
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
                const {style} = component.get('block');
                for (const [target, own] of [[component, style[''] ?? {}], ...within(component).map(one => [one, style[keyOf(one)] ?? {}])]) {
                    for (const screen of screens.filter(one => own[one.id])) {
                        editor.Css.setIdRule(target.getId(), shown(own[screen.id]), {mediaText: media(screen)});
                    }
                }
            }
        });
        saved = JSON.stringify(documentOf());
    });
};

const pieces = elements.map(({kind, label}) => ({
    id: `free-${kind}`,
    label,
    attributes: {'data-element-kind': kind},
    category: root.dataset.elementsLabel,
    content: element({kind, key: '', paragraphs: [[{text: label}]], name: '', label, link: '/', items: []}),
}));

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
    canvasCss: `${root.dataset.base}.${UNAVAILABLE}{padding:1rem;border:1px dashed;opacity:.6}.${FREE_BODY}:empty,[data-gjs-type="${BOX}"]:empty{min-height:3rem;outline:1px dashed}`,
    deviceManager: {devices: screens.map(({id, media, width}) => ({id, name: id, width: width ? `${width}px` : '', widthMedia: media ? `${media}px` : ''}))},
    mediaCondition: 'max-width',
    domComponents: {disableTextInnerChilds: true},
    layerManager: {appendTo: root.querySelector('[data-layers]'), root: `[data-gjs-type="${BLOCKS}"]`},
    styleManager: {appendTo: root.querySelector('[data-style-panel]'), sectors: sectors.map(({id, name, properties}) => ({id, name, open: id === OPEN, properties: properties.map(field)}))},
    traitManager: {appendTo: root.querySelector('[data-traits-panel]')},
    blockManager: {appendTo: root.querySelector('[data-palette]'), blocks: [...kinds.map(kind => ({id: kind.type, label: kind.label, attributes: {'data-type': kind.type}, content: {type: BLOCK, block: kind}})), ...pieces]},
    assetManager: {assets: pictures, upload: root.dataset.upload, uploadName: FILES, params: {[TOKEN]: token()}, showUrlInput: false},
    richTextEditor: {actions: ['bold', 'italic', 'link']},
    modal: {custom: true},
    selectorManager: {componentFirst: true},
    i18n: {locale: document.documentElement.lang, detectLocale: false, localeFallback: 'en', messages: {ru}},
    plugins: [setup],
    components: [...page.body.childNodes].map(part).filter(Boolean),
});

const container = () => editor.getWrapper().findType(BLOCKS)[0];
const placed = () => container().components().filter(component => component.get('type') === BLOCK);
const rulesOf = component => Object.fromEntries(screens
    .map(screen => [screen.id, Object.entries(editor.Css.getIdRule(component.getId(), {mediaText: media(screen)})?.getStyle() ?? {}).filter(([, value]) => value !== '')])
    .filter(([, properties]) => properties.length > 0)
    .map(([screen, properties]) => [screen, sorted(stored(properties))]));
const styleOf = component => {
    const own = rulesOf(component);
    const {type, style} = component.get('block');
    const rest = type === free
        ? within(component).map(one => [keyOf(one), sorted(rulesOf(one))]).filter(([, rules]) => Object.keys(rules).length > 0)
        : Object.entries(style).filter(([target]) => target !== '');

    return sorted(Object.fromEntries(Object.keys(own).length > 0 ? [...rest, ['', sorted(own)]] : rest));
};
const valuesOf = component => {
    const {type, values} = component.get('block');
    if (type !== free) {
        return sorted(values);
    }
    const body = component.findType(BODY)[0];
    const tree = body ? body.components().map(nodeOf) : [];

    return tree.length > 0 ? {elements: JSON.stringify(tree)} : {};
};
const documentOf = () => ({version, blocks: placed().map(component => {
    const {id, type} = component.get('block');

    return {id, type, values: valuesOf(component), style: styleOf(component)};
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

library.addEventListener('click', event => {
    const picked = event.target.closest('[data-library-type]');
    if (picked) {
        library.close();
        fill(null, kinds.find(kind => kind.type === picked.dataset.libraryType), adding);
    }
});

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
