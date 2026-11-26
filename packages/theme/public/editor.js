import {Editor} from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import {Markdown} from '@tiptap/markdown';

const MARKS = {
    bold: ['bold', 'toggleBold'],
    italic: ['italic', 'toggleItalic'],
    bullets: ['bulletList', 'toggleBulletList'],
    numbers: ['orderedList', 'toggleOrderedList'],
    quote: ['blockquote', 'toggleBlockquote'],
};

const editors = new WeakMap();

const paint = (box, editor) => box.querySelectorAll('[data-editor-mark]').forEach(button => {
    button.setAttribute('aria-pressed', String(editor.isActive(MARKS[button.dataset.editorMark][0])));
});

const build = box => {
    const field = box.querySelector('textarea');
    const area = Object.assign(document.createElement('div'), {className: 'editor-area'});
    area.style.setProperty('--rows', field.rows);
    field.after(area);

    const editor = new Editor({
        element: area,
        extensions: [StarterKit.configure({underline: false, link: {openOnClick: false}}), Markdown],
        content: field.value,
        contentType: 'markdown',
        editorProps: {attributes: {class: 'prose', role: 'textbox', 'aria-multiline': 'true', 'aria-label': box.querySelector('label').textContent.trim()}},
        onUpdate: () => {
            field.value = editor.getMarkdown();
        },
        onTransaction: () => paint(box, editor),
    });

    field.required = false;
    field.hidden = true;
    box.querySelector('.editor-bar').hidden = false;
    editors.set(box, {editor, field, area});
};

export const mount = scope => scope.querySelectorAll('[data-editor]').forEach(box => {
    if (!editors.has(box) && !box.closest('dialog:not([open])')) {
        build(box);
    }
});

const source = (box, button) => {
    const {editor, field, area} = editors.get(box);
    const raw = field.hidden;

    if (!raw) {
        editor.commands.setContent(field.value, {contentType: 'markdown', emitUpdate: false});
    }
    field.hidden = !raw;
    area.hidden = raw;
    button.setAttribute('aria-pressed', String(raw));
    box.querySelectorAll('[data-editor-mark]').forEach(mark => mark.disabled = raw);
    raw ? field.focus() : editor.commands.focus();
};

export const insertLine = (field, text) => {
    const box = field.closest('[data-editor]');
    const built = box && editors.get(box);

    if (built && !field.hidden) {
        built.editor.chain().focus().insertContent({type: 'paragraph', content: [{type: 'text', text}]}).run();
        return;
    }

    const at = field.selectionStart;
    const before = field.value.slice(0, at);
    field.setRangeText(`${before && !before.endsWith('\n') ? '\n' : ''}${text}\n`, at, field.selectionEnd, 'end');
    field.focus();
};

document.addEventListener('click', event => {
    const box = event.target.closest('[data-editor]');
    const built = box && editors.get(box);
    if (!built) {
        return;
    }

    const mark = event.target.closest('[data-editor-mark]');
    if (mark) {
        built.editor.chain().focus()[MARKS[mark.dataset.editorMark][1]]().run();
        return;
    }

    const toggle = event.target.closest('[data-editor-source]');
    if (toggle) {
        source(box, toggle);
        return;
    }

    if (event.target.closest('label') && built.field.hidden) {
        event.preventDefault();
        built.editor.commands.focus();
    }
});
