import Card from '@/Components/Card';

function Field({ label, value, className = '' }) {
    return (
        <div className={className}>
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{value || '—'}</dd>
        </div>
    );
}

const at = (who, when) => (who ? `${who}${when ? ` @ ${when}` : ''}` : null);

/**
 * The read-only stock request header shown on the receiver and MCD records screens.
 */
export default function RequestDetails({ stockRequest }) {
    return (
        <Card className="p-6">
            <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <Field label="Date Filed" value={stockRequest.date_filed} />
                <Field label="Time Filed" value={stockRequest.time_filed} />
                <Field label="Date Needed" value={stockRequest.date_needed} />
                <Field label="Requestor Name" value={stockRequest.requestor} />
                <Field label="Stock Transfer From" value={stockRequest.origin} />
                <Field label="Stock Transfer To" value={stockRequest.dept} />
                <Field label="Requested By" value={stockRequest.requested_by} className="sm:col-span-2" />
                <Field label="Remarks" value={stockRequest.remarks} className="sm:col-span-2 lg:col-span-4" />
                {stockRequest.approved_by && (
                    <Field label="Approved By" value={at(stockRequest.approved_by, stockRequest.approved_at)} className="sm:col-span-2" />
                )}
                {stockRequest.received_by && (
                    <Field label="Received By" value={at(stockRequest.received_by, stockRequest.received_at)} className="sm:col-span-2" />
                )}
            </dl>
        </Card>
    );
}

const TONES = {
    success: 'text-green-700 dark:text-green-400',
    warning: 'text-amber-600 dark:text-amber-400',
    info: 'text-sky-700 dark:text-sky-400',
    danger: 'text-red-700 dark:text-red-400',
};

/**
 * Legacy colours an item's served quantity and remark: green completed, orange unserved,
 * blue partially served, red closed.
 */
export function ServeTone({ tone, children }) {
    return <span className={'font-medium ' + (TONES[tone] ?? '')}>{children}</span>;
}
