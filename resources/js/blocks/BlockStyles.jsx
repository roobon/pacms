import { useMemo } from 'react';
import { compileTree } from './style/compile.js';

/**
 * Emits the compiled, scoped stylesheet for a block tree (one <style> per tree).
 * Values are tokens or validated literals only (see style/values.js).
 *
 * @param {{nodes: Array<Object>}} props
 */
export default function BlockStyles({ nodes }) {
    const css = useMemo(() => compileTree(nodes), [nodes]);

    return css ? <style>{css}</style> : null;
}
