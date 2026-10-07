import ButtonLink from '@/Components/ButtonLink';
import Card from '@/Components/Card';
import DataTable from '@/Components/DataTable';
import { PrinterIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import RequestDetails, { ServeTone } from '@/Components/RequestDetails';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ stockRequest, items, backTo, printRoute = 'mcds.print' }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[
                        { label: backTo.startsWith('mcdrecords') ? 'MCD Records' : 'MCD Receiving', href: route(backTo) },
                        { label: 'View Request' },
                    ]}
                    title={stockRequest.transaction_no}
                    subtitle="View Stock Transfer Request"
                    actions={
                        <div className="flex gap-2 print:hidden">
                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="inline-flex items-center gap-1.5 rounded-md bg-brand-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-brand-700 dark:bg-brand-600 dark:hover:bg-brand-500"
                            >
                                <PrinterIcon />
                                Print
                            </button>
                            {stockRequest.isReceived && (
                                <ButtonLink href={route(printRoute, stockRequest.id)} variant="secondary" plain>
                                    <PrinterIcon />
                                    Stock Request Form
                                </ButtonLink>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title={`View ${stockRequest.transaction_no}`} />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <RequestDetails stockRequest={stockRequest} />

                <Card>
                    <div className="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                        <h2 className="font-semibold text-gray-900 dark:text-gray-100">Requested Items</h2>
                    </div>
                    <DataTable
                        headers={[
                            { label: '#' },
                            { label: 'Stock Code' },
                            { label: 'Description' },
                            { label: 'UoM' },
                            { label: 'Requested Qty.' },
                            { label: 'Served Qty.' },
                            { label: 'Remarks' },
                        ]}
                        count={items.length}
                    >
                        {items.map((item, index) => (
                            <DataTable.Row key={item.id}>
                                <DataTable.Cell className="text-gray-500">{index + 1}</DataTable.Cell>
                                <DataTable.Cell strong className="font-mono text-xs">{item.stock_code}</DataTable.Cell>
                                <DataTable.Cell wrap>{item.description}</DataTable.Cell>
                                <DataTable.Cell>{item.uom}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">{item.requested_qty}</DataTable.Cell>
                                <DataTable.Cell className="tabular-nums">
                                    <ServeTone tone={item.serve.tone}>{item.issued_qty}</ServeTone>
                                </DataTable.Cell>
                                <DataTable.Cell wrap>
                                    <ServeTone tone={item.serve.tone}>{item.serve.label}</ServeTone>
                                </DataTable.Cell>
                            </DataTable.Row>
                        ))}
                    </DataTable>
                </Card>

                <Link
                    href={route(backTo)}
                    className="inline-block text-sm print:hidden font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    ← Back to Dashboard
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
