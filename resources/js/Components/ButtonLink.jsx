import { Link } from '@inertiajs/react';

const VARIANTS = {
    primary: 'bg-brand-900 text-white hover:bg-brand-700 dark:bg-brand-600 dark:hover:bg-brand-500',
    secondary:
        'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
};

/**
 * A link styled as a button. `plain` renders an ordinary anchor, needed for file downloads
 * (they must not go through an Inertia visit).
 */
export default function ButtonLink({ href, variant = 'primary', plain = false, className = '', children, ...props }) {
    const classes =
        'inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-xs font-semibold uppercase tracking-widest transition ' +
        VARIANTS[variant] +
        ' ' +
        className;

    return plain ? (
        <a href={href} className={classes} {...props}>
            {children}
        </a>
    ) : (
        <Link href={href} className={classes} {...props}>
            {children}
        </Link>
    );
}
