import { Link } from 'react-router';

/**
 * @param {{crumbs: Array<{title: string, url: string}>|undefined}} props
 */
export default function Breadcrumbs({ crumbs }) {
    if (!crumbs || crumbs.length < 2) return null;

    return (
        <nav aria-label="Breadcrumb">
            <ol className="breadcrumb small">
                {crumbs.map((crumb, index) =>
                    index === crumbs.length - 1 ? (
                        <li key={crumb.url} className="breadcrumb-item active" aria-current="page">
                            {crumb.title}
                        </li>
                    ) : (
                        <li key={crumb.url} className="breadcrumb-item">
                            <Link to={crumb.url}>{crumb.title}</Link>
                        </li>
                    ),
                )}
            </ol>
        </nav>
    );
}
