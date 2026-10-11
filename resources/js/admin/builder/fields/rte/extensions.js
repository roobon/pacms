import { Extension, Mark, Node, mergeAttributes } from '@tiptap/react';

/**
 * Rich text editor extensions (R-1…R-4). Everything is expressed with a fixed list of
 * class names, never inline styles, so the server's allowlist (HtmlSanitizer) can keep it
 * and the website styles it with the theme's colours.
 */

/** Theme colours offered for text and highlights (R-1: theme colours only). */
export const COLOURS = {
    primary: 'Primary',
    secondary: 'Secondary',
    accent: 'Accent',
    muted: 'Muted',
    success: 'Success',
    warning: 'Warning',
    danger: 'Danger',
};

export const BOXES = { info: 'Info box', success: 'Success box', warning: 'Warning box', note: 'Note box' };

export const FIGURE_SIZES = { full: 'Full width', wide: 'Wide', left: 'Left, text wraps', right: 'Right, text wraps' };

function classValue(element, prefix, allowed) {
    const match = [...element.classList].find((name) => name.startsWith(prefix) && allowed.includes(name.slice(prefix.length)));
    return match ? match.slice(prefix.length) : null;
}

/** Alignment of paragraphs and headings: a class, not a style. */
export const ClassAlign = Extension.create({
    name: 'classAlign',
    addGlobalAttributes() {
        return [
            {
                types: ['paragraph', 'heading'],
                attributes: {
                    align: {
                        default: null,
                        parseHTML: (element) => classValue(element, 'pa-align-', ['center', 'end']),
                        renderHTML: (attributes) => (attributes.align ? { class: `pa-align-${attributes.align}` } : {}),
                    },
                },
            },
        ];
    },
    addCommands() {
        return {
            setAlign:
                (align) =>
                ({ commands }) =>
                    ['paragraph', 'heading'].map((type) => commands.updateAttributes(type, { align: align === 'start' ? null : align })).some(Boolean),
        };
    },
});

/** Text colour from the theme: <span class="pa-text-accent">. */
export const TextColour = Mark.create({
    name: 'textColour',
    addAttributes() {
        return { colour: { default: null } };
    },
    parseHTML() {
        return [{ tag: 'span[class]', getAttrs: (element) => ((colour) => (colour ? { colour } : false))(classValue(element, 'pa-text-', Object.keys(COLOURS))) }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['span', { class: `pa-text-${HTMLAttributes.colour}` }, 0];
    },
    addCommands() {
        return {
            setTextColour:
                (colour) =>
                ({ commands }) =>
                    colour ? commands.setMark(this.name, { colour }) : commands.unsetMark(this.name),
        };
    },
});

/** Highlighted text: <mark class="pa-mark-warning">. */
export const Highlight = Mark.create({
    name: 'themeHighlight',
    addAttributes() {
        return { colour: { default: 'warning' } };
    },
    parseHTML() {
        return [{ tag: 'mark', getAttrs: (element) => ({ colour: classValue(element, 'pa-mark-', Object.keys(COLOURS)) ?? 'warning' }) }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['mark', { class: `pa-mark-${HTMLAttributes.colour}` }, 0];
    },
    addCommands() {
        return {
            setHighlight:
                (colour) =>
                ({ commands }) =>
                    colour ? commands.setMark(this.name, { colour }) : commands.unsetMark(this.name),
        };
    },
});

/** A highlight box around paragraphs: <div class="pa-callout pa-callout--info">. */
export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return { tone: { default: 'info' } };
    },
    parseHTML() {
        return [{ tag: 'div.pa-callout', getAttrs: (element) => ({ tone: classValue(element, 'pa-callout--', Object.keys(BOXES)) ?? 'info' }) }];
    },
    renderHTML({ HTMLAttributes }) {
        return ['div', { class: `pa-callout pa-callout--${HTMLAttributes.tone}` }, 0];
    },
    addCommands() {
        return {
            setCallout:
                (tone) =>
                ({ editor, commands }) => {
                    if (editor.isActive(this.name)) {
                        return tone ? commands.updateAttributes(this.name, { tone }) : commands.lift(this.name);
                    }
                    return tone ? commands.wrapIn(this.name, { tone }) : false;
                },
        };
    },
});

/**
 * An image from the Media Library (R-2) with alt text, an optional caption and a size or
 * position: <figure class="pa-figure pa-figure--wide"><img …><figcaption>…</figcaption></figure>.
 */
export const Figure = Node.create({
    name: 'figure',
    group: 'block',
    atom: true,
    draggable: true,
    selectable: true,
    addAttributes() {
        return {
            src: { default: null },
            alt: { default: '' },
            caption: { default: '' },
            size: { default: 'full' },
            width: { default: null },
            height: { default: null },
            mediaId: { default: null },
        };
    },
    parseHTML() {
        const fromImage = (image) => ({
            src: image?.getAttribute('src'),
            alt: image?.getAttribute('alt') ?? '',
            width: image?.getAttribute('width'),
            height: image?.getAttribute('height'),
            mediaId: image?.getAttribute('data-media'),
        });
        return [
            {
                tag: 'figure',
                getAttrs: (element) => {
                    const image = element.querySelector('img');
                    if (!image) return false;
                    return { ...fromImage(image), caption: element.querySelector('figcaption')?.textContent ?? '', size: classValue(element, 'pa-figure--', Object.keys(FIGURE_SIZES)) ?? 'full' };
                },
            },
            { tag: 'img[src]', getAttrs: (element) => fromImage(element) },
        ];
    },
    renderHTML({ HTMLAttributes }) {
        const { src, alt, caption, size, width, height, mediaId } = HTMLAttributes;
        const image = ['img', mergeAttributes({ src, alt: alt ?? '' }, width ? { width } : {}, height ? { height } : {}, mediaId ? { 'data-media': mediaId } : {})];
        return ['figure', { class: `pa-figure pa-figure--${size ?? 'full'}` }, image, ...(caption ? [['figcaption', {}, caption]] : [])];
    },
    addCommands() {
        return {
            insertFigure:
                (attributes) =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name, attrs: attributes }),
        };
    },
});

/** Words in the text (shown under the editor). */
export function wordCount(text) {
    const words = text.trim().match(/[\p{L}\p{N}][\p{L}\p{N}'’-]*/gu);
    return words ? words.length : 0;
}
