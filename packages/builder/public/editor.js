import grapesjs from 'grapesjs';
import ru from 'grapesjs/locale/ru';

const PART = 'frame-part';
const BLOCKS = 'page-blocks';
const BLOCK = 'ready-block';
const SKIPPED = 'script, template, noscript';
const SHEETS = 'link[rel="stylesheet"]';
const HEAD = 'link[rel="preload"], style';
const LOCKED = {selectable: false, hoverable: false, highlightable: false, draggable: false, droppable: false, removable: false, copyable: false, layerable: false, editable: false, stylable: false, badgable: false};
const UNAVAILABLE = 'builder-unavailable';

const root = document.querySelector('[data-builder]');
const blocks = JSON.parse(root.dataset.blocks);
const screens = JSON.parse(root.dataset.screens);
const anchors = new Set(blocks.map(block => block.anchor));

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

const setup = editor => {
    const types = editor.DomComponents;
    types.addType(PART, {model: {defaults: LOCKED}});
    types.addType(BLOCKS, {model: {defaults: {...LOCKED, layerable: true}}});
    types.addType(BLOCK, {model: {defaults: {draggable: false, droppable: false, removable: false, copyable: false, editable: false, stylable: false, toolbar: []}}});

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

root.addEventListener('click', event => {
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
