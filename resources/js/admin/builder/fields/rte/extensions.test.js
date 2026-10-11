import { describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { Callout, ClassAlign, Figure, Highlight, TextColour, wordCount } from './extensions.js';

function editorWith(content) {
    return new Editor({
        extensions: [StarterKit.configure({ link: false }), Table, TableRow, TableHeader, TableCell, ClassAlign, TextColour, Highlight, Callout, Figure],
        content,
    });
}

describe('rich text extensions', () => {
    it('keep theme colours, highlights and alignment as classes, never styles', () => {
        const editor = editorWith('<p class="pa-align-center">A <span class="pa-text-accent">word</span> and <mark class="pa-mark-success">this</mark></p><p style="color:red">styled</p>');
        const html = editor.getHTML();
        expect(html).toContain('<p class="pa-align-center">');
        expect(html).toContain('<span class="pa-text-accent">word</span>');
        expect(html).toContain('<mark class="pa-mark-success">this</mark>');
        expect(html).not.toContain('style=');

        editor.chain().selectAll().setAlign('end').run();
        expect(editor.getHTML()).toContain('class="pa-align-end"');
    });

    it('wraps paragraphs in a highlight box and changes or removes it', () => {
        const editor = editorWith('<p>Key fact</p>');
        editor.chain().setTextSelection(1).setCallout('warning').run();
        // (TipTap keeps an empty paragraph after the box, so writing can continue below it.)
        expect(editor.getHTML()).toContain('<div class="pa-callout pa-callout--warning"><p>Key fact</p></div>');
        editor.chain().setCallout('info').run();
        expect(editor.getHTML()).toContain('pa-callout--info');
        editor.chain().setCallout(null).run();
        expect(editor.getHTML()).not.toContain('pa-callout');
    });

    it('stores library images as figures with size, alt text and caption', () => {
        const editor = editorWith('<p>Text</p>');
        editor.chain().insertFigure({ src: '/storage/media/a.jpg', alt: 'Planting', caption: 'Satkhira, 2026', size: 'left', mediaId: 7, width: 1280, height: 720 }).run();
        expect(editor.getHTML()).toContain('<figure class="pa-figure pa-figure--left"><img src="/storage/media/a.jpg" alt="Planting" width="1280" height="720" data-media="7"><figcaption>Satkhira, 2026</figcaption></figure>');

        // Read back the same way (editing an existing article).
        const again = editorWith(editor.getHTML());
        expect(again.getHTML()).toBe(editor.getHTML());
    });

    it('edits tables', () => {
        const editor = editorWith('<p></p>');
        editor.chain().insertTable({ rows: 2, cols: 2, withHeaderRow: true }).run();
        expect(editor.getHTML()).toContain('<th');
        expect(editor.getHTML().match(/<tr>/g)).toHaveLength(2);
    });

    it('counts words', () => {
        expect(wordCount('Mangroves protect 3 villages — don’t they?')).toBe(6);
        expect(wordCount('   ')).toBe(0);
    });
});
