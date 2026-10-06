import { createContext, useContext } from 'react';
import { blockClass } from '../style/compile.js';

/** True inside the builder's live preview (adds selection hooks, shows hidden blocks). */
export const PreviewContext = createContext(false);

export function useIsPreview() {
    return useContext(PreviewContext);
}

const ATTRIBUTE = /^(data-[a-z0-9-]+|aria-[a-z0-9-]+|role|title|lang)$/;

/**
 * Root-element props for a block: scoped class, extra classes, anchor id, allowed custom
 * attributes and (in preview) the block's uuid for click-to-select.
 *
 * @param {Object} node
 * @param {boolean} preview
 * @param {string} [extraClass]
 */
export function frameProps(node, preview, extraClass = '') {
    const advanced = node.advanced ?? {};
    const classes = [blockClass(node.uuid), extraClass, ...(Array.isArray(advanced.classes) ? advanced.classes : [])];

    if (node.hidden) classes.push('pa-block-hidden');
    const animation = node.style?.animation?.type;
    if (animation && animation !== 'none') classes.push(`pa-animate pa-animate--${animation}`);

    const props = { className: classes.filter(Boolean).join(' ') };

    if (typeof advanced.anchor === 'string' && /^[a-z][a-z0-9-]*$/.test(advanced.anchor)) {
        props.id = advanced.anchor;
    }

    for (const [name, value] of Object.entries(advanced.attributes ?? {})) {
        if (ATTRIBUTE.test(name)) props[name] = String(value);
    }

    // Locked blocks (inside a global or custom block) select their owner when clicked.
    if (preview && !node.locked) props['data-block-uuid'] = node.uuid;

    return props;
}

const CONTAINER_CLASSES = {
    boxed: 'container',
    narrow: 'container pa-container-narrow',
    fluid: 'container-fluid px-3 px-lg-4',
    full: '',
};

/** Inner wrapper class for section-like blocks (layout.container). */
export function containerClass(node) {
    return CONTAINER_CLASSES[node.layout?.container] ?? CONTAINER_CLASSES.boxed;
}
