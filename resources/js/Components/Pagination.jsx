import { Link } from '@inertiajs/react';

/**
 * Renders a Laravel length-aware paginator (the object Inertia receives from ->paginate()).
 */
export default function Pagination({ paginator }) {
    const { from, to, total, links } = paginator;

    if (!total) {
        return null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t border-gray-200 px-4 py-3 text-sm sm:flex-row dark:border-gray-800">
            <p className="text-gray-600 dark:text-gray-400">
                Showing <span className="font-medium">{from}</span> to{' '}
                <span className="font-medium">{to}</span> of{' '}
                <span className="font-medium">{total}</span>
            </p>

            {links.length > 3 && (
                <nav aria-label="Pagination" className="flex flex-wrap gap-1">
                    {links.map((link, index) => {
                        const label = link.label
                            .replace('&laquo; Previous', '‹')
                            .replace('Next &raquo;', '›');

                        const base = 'min-w-[2rem] rounded-md px-2.5 py-1 text-center';

                        return link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                preserveState
                                aria-current={link.active ? 'page' : undefined}
                                className={
                                    base +
                                    (link.active
                                        ? ' bg-brand-900 text-white dark:bg-brand-600'
                                        : ' text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800')
                                }
                            >
                                {label}
                            </Link>
                        ) : (
                            <span key={index} className={base + ' text-gray-400 dark:text-gray-600'}>
                                {label}
                            </span>
                        );
                    })}
                </nav>
            )}
        </div>
    );
}
