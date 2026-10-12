/** Marks the query's words in a result's text (plain text in, React nodes out: no HTML injected). */
export function highlight(text, query) {
    const words = query
        .split(/\s+/)
        .map((word) => word.replace(/[^\p{L}\p{N}]/gu, ''))
        .filter((word) => word.length >= 2);
    if (!text || words.length === 0) return text;
    const pattern = new RegExp(`(${words.map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'giu');
    return text.split(pattern).map((part, index) => (index % 2 === 1 ? <mark key={index}>{part}</mark> : part));
}
