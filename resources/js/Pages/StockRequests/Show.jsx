import Card from '@/Components/Card';
import { PrinterIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import RequestStatusBadge from '@/Components/RequestStatusBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function Field({ label, value, className = '' }) {
    return (
        <div className={className}>
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{value || '—'}</dd>
        </div>
    );
}

export default function Show({ stockRequest, items }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: 'Stock Request' },
                        { label: 'Manage Requests', href: route('stockrequests.index') },
                        { label: 'View Request' },
                    ]}
                    title={stockRequest.transaction_no}
                    subtitle={<RequestStatusBadge badge={stockRequest.badge} />}
                    actions={
                        <a
                            href={route('stockrequests.print', stockRequest.id)}
                            className="inline-flex items-center gap-1.5 rounded-md bg-brand-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-brand-700 dark:bg-brand-600 dark:hover:bg-brand-500"
                        >
                            <PrinterIcon />
                            Print
                        </a>
                    }
                />
            }
        >
            <Head title={`View ${stockRequest.transaction_no}`} />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <Card className="p-6">
                    <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="Date Filed" value={stockRequest.date_filed} />
                        <Field label="Time Filed" value={stockRequest.time_filed} />
                        <Field label="Date Needed" value={stockRequest.date_needed} />
                        <Field label="Requestor Name" value={stockRequest.requestor} />
                        <Field label="Stock Transfer From" value={stockRequest.origin} />
                        <Field label="Stock Transfer To" value={stockRequest.dept} />
                        <Field label="Created By" value={stockRequest.requested_by} className="sm:col-span-2" />
                        <Field label="Remarks" value={stockRequest.remarks} className="sm:col-span-2 lg:col-span-4" />
                        {stockRequest.approved_by && (
                            <Field label="Approved By" value={`${stockRequest.approved_by}${stockRequest.approved_at ? ` @ ${stockRequest.approved_at}` : ''}`} className="sm:col-span-2" />
                        )}
                        {stockRequest.received_by && (
                            <Field label="Received By" value={`${stockRequest.received_by}${stockRequest.received_at ? ` @ ${stockRequest.received_at}` : ''}`} className="sm:col-span-2" />
                        )}
                    </dl>
                </Card>

                <Card>
                    <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Requested Items</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                            <thead className="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    {['#', 'Stock Code', 'Description', 'UoM', 'Requested Qty.', 'Remarks'].map((heading) => (
                                        <th key={heading} scope="col" className="whitespace-nowrap px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {items.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                            No record found.
                                        </td>
                                    </tr>
                                )}
                                {items.map((item, index) => (
                                    <tr key={item.id}>
                                        <td className="px-4 py-2 text-gray-500 dark:text-gray-400">{index + 1}</td>
                                        <td className="whitespace-nowrap px-4 py-2 font-mono text-xs text-gray-900 dark:text-gray-100">{item.stock_code}</td>
                                        <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{item.description}</td>
                                        <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{item.uom}</td>
                                        <td className="px-4 py-2 tabular-nums text-gray-700 dark:text-gray-300">{item.requested_qty}</td>
                                        <td className="px-4 py-2 text-gray-700 dark:text-gray-300">{item.remarks}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>

                <Link
                    href={route('stockrequests.index')}
                    className="inline-block text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    ← Back to Dashboard
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
