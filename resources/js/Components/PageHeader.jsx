import { Link } from '@inertiajs/react';

/**
 * breadcrumbs: [{ label, href? }]
 */
export default function PageHeader({ breadcrumbs = [], title, subtitle, actions }) {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                {breadcrumbs.length > 0 && (
                    <nav aria-label="Breadcrumb" className="mb-1">
                        <ol className="flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            {breadcrumbs.map((crumb, index) => (
                                <li key={crumb.label} className="flex items-center gap-1">
                                    {index > 0 && <span aria-hidden="true">/</span>}
                                    {crumb.href ? (
                                        <Link href={crumb.href} className="hover:text-gray-800 dark:hover:text-gray-200">
                                            {crumb.label}
                                        </Link>
                                    ) : (
                                        <span>{crumb.label}</span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </nav>
                )}
                <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{title}</h1>
                {subtitle && (
                    <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>
                )}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}
