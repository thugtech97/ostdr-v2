import { Link } from '@inertiajs/react';

const TONES = {
    neutral: 'text-gray-500 hover:bg-brand-50 hover:text-brand-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white',
    danger: 'text-gray-500 hover:bg-red-50 hover:text-red-700 dark:text-gray-400 dark:hover:bg-red-950 dark:hover:text-red-300',
    success: 'text-gray-500 hover:bg-green-50 hover:text-green-700 dark:text-gray-400 dark:hover:bg-green-950 dark:hover:text-green-300',
};

/**
 * Icon-only action. `label` is required: it becomes the tooltip and the accessible name.
 * Pass `href` to render an Inertia link instead of a button, plus `download` for a plain
 * link (file downloads must not go through an Inertia visit).
 */
export default function IconButton({ label, tone = 'neutral', href, download = false, className = '', children, ...props }) {
    const classes =
        'group relative inline-flex h-8 w-8 items-center justify-center rounded-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ' +
        TONES[tone] +
        ' ' +
        className;

    const tooltip = (
        <span
            role="tooltip"
            className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs font-medium text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100 dark:bg-gray-700"
        >
            {label}
        </span>
    );

    if (href && download) {
        return (
            <a href={href} aria-label={label} className={classes} {...props}>
                {children}
                {tooltip}
            </a>
        );
    }

    return href ? (
        <Link href={href} aria-label={label} className={classes} {...props}>
            {children}
            {tooltip}
        </Link>
    ) : (
        <button type="button" aria-label={label} className={classes} {...props}>
            {children}
            {tooltip}
        </button>
    );
}
