import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const HOUR = 60 * 60 * 1000;

function remaining(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    return [days ? `${days}d` : null, `${hours}h`, `${minutes}m`, `${seconds}s`].filter(Boolean).join(' ');
}

/**
 * Legacy's scheduled-shutdown notice: a banner with a countdown for a shutdown in the
 * next five days, and a one-time warning once it is less than an hour away.
 */
export default function ShutdownBanner() {
    const { shutdown } = usePage().props;
    const [now, setNow] = useState(() => Date.now());
    const [warned, setWarned] = useState(false);
    const [warning, setWarning] = useState(false);

    const at = shutdown ? new Date(shutdown.at).getTime() : null;

    useEffect(() => {
        if (!at) {
            return;
        }

        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, [at]);

    useEffect(() => {
        if (at && !warned && at - now <= HOUR && at > now) {
            setWarned(true);
            setWarning(true);
        }
    }, [at, now, warned]);

    if (!at || at <= now) {
        return null;
    }

    const when = new Date(at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

    return (
        <>
            <div role="status" className="flex flex-col gap-1 border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <div>
                    <p className="font-semibold">{shutdown.reason}</p>
                    <p className="text-xs">Shutdown Date: {when}</p>
                </div>
                <p className="font-semibold tabular-nums">Time Remaining: {remaining(at - now)}</p>
            </div>

            <Modal show={warning} maxWidth="md" onClose={() => setWarning(false)}>
                <div className="p-6 dark:bg-gray-900">
                    <p className="text-lg font-bold text-gray-900 dark:text-gray-100">
                        In less than 1 hour the system will undergo maintenance! Please save your work!
                    </p>
                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={() => setWarning(false)}>OK</SecondaryButton>
                    </div>
                </div>
            </Modal>
        </>
    );
}
