const VARIANTS = {
    success: 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-900/30 dark:text-green-300 dark:ring-green-400/20',
    warning: 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-400/20',
    danger: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-400/20',
    info: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-400/20',
    primary: 'bg-brand-50 text-brand-800 ring-brand-600/20 dark:bg-brand-900/40 dark:text-brand-200 dark:ring-brand-400/20',
    muted: 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-400/20',
};

/**
 * Renders the status badge computed server-side (StockRequest::statusBadge).
 */
export default function RequestStatusBadge({ badge }) {
    if (!badge) {
        return null;
    }

    return (
        <span
            className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${VARIANTS[badge.variant] ?? VARIANTS.muted}`}
        >
            {badge.label}
            {badge.note && (
                <em className={'not-italic ' + (badge.alert ? 'font-semibold text-red-700 dark:text-red-300' : 'opacity-80')}>
                    · {badge.note}
                </em>
            )}
        </span>
    );
}
