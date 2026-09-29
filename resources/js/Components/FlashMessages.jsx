import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function FlashMessages() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(null);

    useEffect(() => {
        if (!flash.success && !flash.error) {
            return;
        }

        setVisible(
            flash.error
                ? { type: 'error', text: flash.error }
                : { type: 'success', text: flash.success },
        );

        const timer = setTimeout(() => setVisible(null), 4000);

        return () => clearTimeout(timer);
    }, [flash]);

    if (!visible) {
        return null;
    }

    const styles =
        visible.type === 'error'
            ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200'
            : 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200';

    return (
        <div
            role="status"
            className={`fixed right-4 top-4 z-50 flex max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg ${styles}`}
        >
            <span className="flex-1">{visible.text}</span>
            <button
                type="button"
                onClick={() => setVisible(null)}
                className="opacity-60 hover:opacity-100"
                aria-label="Dismiss"
            >
                &times;
            </button>
        </div>
    );
}
