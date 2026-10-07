import Card from '@/Components/Card';
import Checkbox from '@/Components/Checkbox';
import PrimaryButton from '@/Components/PrimaryButton';
import { Link } from '@inertiajs/react';

/**
 * Shell for the small maintenance forms (satellites, permissions): a "Publish" toggle,
 * the fields, then Save / Cancel.
 */
export default function MaintenanceForm({ onSubmit, active, onActiveChange, processing, submitLabel, cancelHref, children }) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <Card className="space-y-5 p-6">
                <label className="flex items-center gap-3">
                    <Checkbox checked={active} onChange={(e) => onActiveChange(e.target.checked)} />
                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Publish</span>
                </label>
                {children}
            </Card>

            <div className="flex items-center gap-3">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                <Link href={cancelHref} className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                    Cancel
                </Link>
            </div>
        </form>
    );
}
