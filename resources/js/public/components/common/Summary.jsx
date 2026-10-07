/**
 * A page or news summary under the title. `html` is limited formatting (paragraphs, bold,
 * italic, links) cleaned by the server; `text` is the plain fallback.
 *
 * @param {{html?: string|null, text?: string|null, className?: string}} props
 */
export default function Summary({ html, text, className = '' }) {
    if (html) {
        return <div className={`${className} pa-summary`} dangerouslySetInnerHTML={{ __html: html }} />;
    }

    return text ? <p className={className}>{text}</p> : null;
}
