export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-gray-300 text-brand-900 shadow-sm focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900 dark:text-brand-500 dark:focus:ring-offset-gray-900 ' +
                className
            }
        />
    );
}
