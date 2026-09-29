import { DownloadIcon, PlusIcon } from '@/Components/Icons';
import PageHeader from '@/Components/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import RequestList from './Partials/RequestList';

export default function Index({ requests, filters, can }) {
    const isFiltered = Boolean(filters.date_from || filters.date_to || filters.search);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    breadcrumbs={[{ label: 'Stock Request' }, { label: 'Manage Requests' }]}
                    title="Manage Stock Requests"
                    subtitle={
                        requests.total
                            ? `${requests.total} request${requests.total === 1 ? '' : 's'} ${isFiltered ? 'matching your filters' : 'in total'}`
                            : 'Browse, edit and print your stock requests.'
                    }
                    actions={
                        <>
                            {requests.total > 0 && (
                                <a
                                    href={route('stockrequests.export', filters)}
                                    className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    <DownloadIcon />
                                    Export
                                </a>
                            )}
                            {can.create && (
                                <Link
                                    href={route('stockrequests.create')}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-brand-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-brand-700 dark:bg-brand-600 dark:hover:bg-brand-500"
                                >
                                    <PlusIcon />
                                    New Request
                                </Link>
                            )}
                        </>
                    }
                />
            }
        >
            <Head title="Manage Stock Requests" />

            <div className="p-4 sm:p-6 lg:p-8">
                <RequestList requests={requests} filters={filters} can={can} routeName="stockrequests.index" />
            </div>
        </AuthenticatedLayout>
    );
}
