export default function StatusBadge({ active }) {
    return active ? (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-300 dark:ring-green-400/20">
            <span className="h-1.5 w-1.5 rounded-full bg-green-500" aria-hidden="true" />
            Active
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-400/20">
            <span className="h-1.5 w-1.5 rounded-full bg-gray-400" aria-hidden="true" />
            Inactive
        </span>
    );
}
