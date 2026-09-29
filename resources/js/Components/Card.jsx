export default function Card({ className = '', children }) {
    return (
        <div
            className={
                'overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800 ' +
                className
            }
        >
            {children}
        </div>
    );
}
